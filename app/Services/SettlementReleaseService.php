<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TenantWallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettlementReleaseService
{
    /**
     * Libera créditos pendentes cujo clears_at já passou e o pedido não impede liberação.
     */
    public static function releaseDue(?int $limit = 500): int
    {
        if (! Schema::hasTable('wallet_transactions') || ! Schema::hasTable('tenant_wallets')) {
            return 0;
        }

        $released = 0;
        $processed = 0;

        WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_CREDIT_SALE_PENDING)
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$released, &$processed, $limit) {
                foreach ($chunk as $tx) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }
                    $processed++;
                    $meta = is_array($tx->meta) ? $tx->meta : [];
                    if (! empty($meta['released_at'])) {
                        continue;
                    }
                    $clearsAt = $meta['clears_at'] ?? null;
                    if ($clearsAt === null || $clearsAt === '') {
                        continue;
                    }
                    try {
                        $when = Carbon::parse($clearsAt);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($when->isFuture()) {
                        continue;
                    }

                    $order = Order::query()->find($tx->order_id);
                    if ($order === null) {
                        continue;
                    }
                    if (in_array($order->status, ['disputed', 'refunded', 'cancelled'], true)) {
                        continue;
                    }

                    if (self::releaseOne($tx->fresh())) {
                        $released++;
                    }
                }

                return true;
            });

        return $released;
    }

    public static function releaseOne(WalletTransaction $tx): bool
    {
        return DB::transaction(function () use ($tx) {
            $tx->refresh();
            $meta = is_array($tx->meta) ? $tx->meta : [];
            if (! empty($meta['released_at'])) {
                return false;
            }

            $tenantId = (int) $tx->tenant_id;
            if ($tenantId < 1) {
                return false;
            }

            $bucket = (string) $tx->bucket;
            $pendCol = 'pending_'.$bucket;
            $availCol = 'available_'.$bucket;
            if (! in_array($pendCol, ['pending_pix', 'pending_card', 'pending_boleto'], true)) {
                $pendCol = 'pending_pix';
                $availCol = 'available_pix';
            }

            $amount = (float) $tx->amount_net;
            if ($amount <= 0) {
                return false;
            }

            $wallet = TenantWallet::query()->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if ($wallet === null) {
                return false;
            }

            $pending = (float) ($wallet->{$pendCol} ?? 0);
            $move = min($amount, max(0, $pending));
            if ($move <= 0) {
                return false;
            }

            $wallet->{$pendCol} = round($pending - $move, 2);
            $wallet->{$availCol} = round((float) ($wallet->{$availCol} ?? 0) + $move, 2);
            self::syncAggregates($wallet);
            $wallet->save();

            $saleTx = WalletTransaction::query()->create([
                'tenant_id' => $tenantId,
                'order_id' => $tx->order_id,
                'withdrawal_id' => null,
                'bucket' => $bucket,
                'type' => WalletTransaction::TYPE_CREDIT_SALE,
                'amount_gross' => (float) $tx->amount_gross,
                'amount_fee' => (float) $tx->amount_fee,
                'amount_net' => $move,
                'meta' => array_merge($meta, [
                    'from_pending_wallet_transaction_id' => $tx->id,
                    'portion' => $meta['portion'] ?? 'main',
                ]),
            ]);

            $meta['released_at'] = now()->toIso8601String();
            $meta['released_to_wallet_transaction_id'] = $saleTx->id;
            $tx->meta = $meta;
            $tx->save();

            return true;
        });
    }

    private static function syncAggregates(TenantWallet $wallet): void
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
