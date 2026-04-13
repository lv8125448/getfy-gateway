<?php

namespace App\Services;

use App\Events\OrderCancelled;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\TenantWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PlatformOrderAdminService
{
    public static function cancelPending(Order $order): void
    {
        if ($order->status !== 'pending') {
            throw new InvalidArgumentException('Só é possível cancelar pedidos pendentes.');
        }

        $order->update(['status' => 'cancelled']);
        event(new OrderCancelled($order->fresh()));
    }

    /**
     * Marca contestação (MED). Pedido pendente ou pago.
     * Se já estava pago e creditado na carteira, o valor líquido passa de disponível para pendente (bloqueio temporário).
     */
    public static function markDisputed(Order $order): void
    {
        if ($order->status === 'disputed') {
            throw new InvalidArgumentException('Este pedido já está em MED.');
        }

        if (! in_array($order->status, ['pending', 'completed'], true)) {
            throw new InvalidArgumentException('Só é possível marcar como MED pedidos pendentes ou pagos.');
        }

        DB::transaction(function () use ($order) {
            if ($order->status === 'completed') {
                self::moveAvailableToPendingForMed($order);
            }
            $order->update(['status' => 'disputed']);
        });
    }

    /**
     * Reembolso manual: pedido pago ou em MED; estorno na carteira (primeiro do pendente, depois do disponível).
     */
    public static function refundPaidOrDisputed(Order $order): void
    {
        if (! in_array($order->status, ['completed', 'disputed'], true)) {
            throw new InvalidArgumentException('Só é possível reembolsar pedidos pagos ou em MED.');
        }

        DB::transaction(function () use ($order) {
            self::reverseSaleCreditIfExists($order);
            $order->update(['status' => 'refunded']);
            event(new OrderRefunded($order->fresh()));
        });
    }

    /**
     * @deprecated Use {@see refundPaidOrDisputed}
     */
    public static function refundCompleted(Order $order): void
    {
        self::refundPaidOrDisputed($order);
    }

    /**
     * Bloqueia o crédito da venda: move de available_* para pending_* (MED), por tenant (co-produção).
     */
    private static function moveAvailableToPendingForMed(Order $order): void
    {
        if (! Schema::hasTable('tenant_wallets') || ! Schema::hasTable('wallet_transactions')) {
            return;
        }
        if (! Schema::hasColumn('tenant_wallets', 'available_pix') || ! Schema::hasColumn('tenant_wallets', 'pending_pix')) {
            return;
        }

        $availableCredits = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', WalletTransaction::TYPE_CREDIT_SALE)
            ->get();

        $pendingCredits = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', WalletTransaction::TYPE_CREDIT_SALE_PENDING)
            ->get()
            ->filter(function ($tx) {
                $m = is_array($tx->meta) ? $tx->meta : [];

                return empty($m['released_at']);
            });

        if ($availableCredits->isEmpty() && $pendingCredits->isEmpty()) {
            return;
        }

        $tenantIds = $availableCredits->pluck('tenant_id')
            ->merge($pendingCredits->pluck('tenant_id'))
            ->unique()
            ->filter(fn ($id) => (int) $id > 0);

        foreach ($tenantIds as $tenantId) {
            $tid = (int) $tenantId;
            $credit = $availableCredits->firstWhere('tenant_id', $tid);
            $pending = $pendingCredits->where('tenant_id', $tid)->values();
            self::moveAvailableToPendingForMedTenant($order, $tid, $credit, $pending);
        }
    }

    private static function moveAvailableToPendingForMedTenant(Order $order, int $tenantId, ?WalletTransaction $credit, Collection $pendingCredits): void
    {
        if ($tenantId < 1) {
            return;
        }

        if (WalletTransaction::query()
            ->where('order_id', $order->id)
            ->where('tenant_id', $tenantId)
            ->where('type', WalletTransaction::TYPE_MED_HOLD)
            ->exists()) {
            return;
        }

        if ($credit === null && $pendingCredits->isEmpty()) {
            return;
        }

        $bucket = $credit !== null
            ? (string) $credit->bucket
            : (string) $pendingCredits->first()->bucket;

        $net = $credit !== null
            ? (float) $credit->amount_net
            : (float) $pendingCredits->sum('amount_net');

        $availCol = 'available_'.$bucket;
        $pendCol = 'pending_'.$bucket;
        if (! in_array($availCol, ['available_pix', 'available_card', 'available_boleto'], true)) {
            $availCol = 'available_pix';
            $pendCol = 'pending_pix';
        }

        $wallet = TenantWallet::query()->where('tenant_id', $tenantId)->lockForUpdate()->first();
        if ($wallet === null) {
            return;
        }

        if ($credit !== null) {
            $available = (float) ($wallet->{$availCol} ?? 0);
            $move = min($net, max(0, $available));
            if ($move > 0) {
                $wallet->{$availCol} = round($available - $move, 2);
                $wallet->{$pendCol} = round((float) ($wallet->{$pendCol} ?? 0) + $move, 2);
            }
        }

        self::recalcWalletAggregates($wallet);
        $wallet->save();

        $grossRef = $credit !== null ? (float) $credit->amount_gross : (float) $pendingCredits->sum('amount_gross');
        $feeRef = $credit !== null ? (float) $credit->amount_fee : (float) $pendingCredits->sum('amount_fee');

        WalletTransaction::query()->create([
            'tenant_id' => $tenantId,
            'order_id' => $order->id,
            'withdrawal_id' => null,
            'bucket' => $bucket,
            'type' => WalletTransaction::TYPE_MED_HOLD,
            'amount_gross' => $grossRef,
            'amount_fee' => $feeRef,
            'amount_net' => $net,
            'meta' => [
                'credit_sale_wallet_transaction_id' => $credit?->id,
                'credit_sale_pending_ids' => $pendingCredits->pluck('id')->values()->all(),
                'reason' => 'med_dispute_hold',
            ],
        ]);
    }

    private static function reverseSaleCreditIfExists(Order $order): void
    {
        if (! Schema::hasTable('tenant_wallets') || ! Schema::hasTable('wallet_transactions')) {
            return;
        }
        if (! Schema::hasColumn('tenant_wallets', 'available_pix')) {
            return;
        }

        $lines = WalletTransaction::query()
            ->where('order_id', $order->id)
            ->whereIn('type', [WalletTransaction::TYPE_CREDIT_SALE, WalletTransaction::TYPE_CREDIT_SALE_PENDING])
            ->orderBy('id')
            ->get()
            ->filter(function ($line) {
                if ($line->type === WalletTransaction::TYPE_CREDIT_SALE) {
                    return true;
                }
                $m = is_array($line->meta) ? $line->meta : [];

                return empty($m['released_at']);
            });

        if ($lines->isEmpty()) {
            return;
        }

        foreach ($lines->groupBy('tenant_id') as $tenantId => $tenantLines) {
            self::reverseSaleCreditsForTenant($order, (int) $tenantId, $tenantLines);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WalletTransaction>  $lines
     */
    private static function reverseSaleCreditsForTenant(Order $order, int $tenantId, Collection $lines): void
    {
        if ($tenantId < 1 || $lines->isEmpty()) {
            return;
        }

        $bucket = (string) $lines->first()->bucket;
        $availCol = 'available_'.$bucket;
        $pendCol = 'pending_'.$bucket;
        if (! in_array($availCol, ['available_pix', 'available_card', 'available_boleto'], true)) {
            $availCol = 'available_pix';
            $pendCol = 'pending_pix';
        }

        $totalNet = 0.0;
        $totalGross = 0.0;
        $totalFee = 0.0;
        $refIds = [];

        DB::transaction(function () use ($lines, $tenantId, $availCol, $pendCol, &$totalNet, &$totalGross, &$totalFee, &$refIds) {
            $wallet = TenantWallet::query()->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if ($wallet === null) {
                return;
            }

            foreach ($lines as $line) {
                $n = (float) $line->amount_net;
                if ($n <= 0) {
                    continue;
                }
                $refIds[] = $line->id;
                $totalNet += $n;
                $totalGross += (float) $line->amount_gross;
                $totalFee += (float) $line->amount_fee;

                if ($line->type === WalletTransaction::TYPE_CREDIT_SALE_PENDING) {
                    $pending = (float) ($wallet->{$pendCol} ?? 0);
                    $take = min($n, max(0, $pending));
                    $wallet->{$pendCol} = round($pending - $take, 2);
                } elseif ($line->type === WalletTransaction::TYPE_CREDIT_SALE) {
                    $available = (float) ($wallet->{$availCol} ?? 0);
                    $take = min($n, max(0, $available));
                    $wallet->{$availCol} = round($available - $take, 2);
                }
            }

            self::recalcWalletAggregates($wallet);
            $wallet->save();
        });

        if ($totalNet <= 0 || $refIds === []) {
            return;
        }

        WalletTransaction::query()->create([
            'tenant_id' => $tenantId,
            'order_id' => $order->id,
            'withdrawal_id' => null,
            'bucket' => $bucket,
            'type' => WalletTransaction::TYPE_DEBIT_REFUND,
            'amount_gross' => round($totalGross, 2),
            'amount_fee' => round($totalFee, 2),
            'amount_net' => round($totalNet, 2),
            'meta' => [
                'reverses_wallet_transaction_ids' => $refIds,
                'reason' => 'platform_manual_refund',
            ],
        ]);
    }

    private static function recalcWalletAggregates(TenantWallet $wallet): void
    {
        if (Schema::hasColumn('tenant_wallets', 'available_balance')) {
            $wallet->available_balance = round(
                (float) ($wallet->available_pix ?? 0)
                + (float) ($wallet->available_card ?? 0)
                + (float) ($wallet->available_boleto ?? 0),
                2
            );
        }
        if (Schema::hasColumn('tenant_wallets', 'pending_balance')) {
            $wallet->pending_balance = round(
                (float) ($wallet->pending_pix ?? 0)
                + (float) ($wallet->pending_card ?? 0)
                + (float) ($wallet->pending_boleto ?? 0),
                2
            );
        }
    }
}
