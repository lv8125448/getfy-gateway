<?php

namespace App\Services\Platform;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\Payout\GatewayPayoutEconomics;
use App\Services\Payout\PlatformPayoutGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PlatformRevenueKpis
{
    /**
     * @return array{
     *     faturamento_taxas_cobradas: float,
     *     faturamento_custo_adquirente_vendas: float,
     *     faturamento_custo_adquirente_saques: float,
     *     faturamento_liquido: float
     * }
     */
    public static function compute(?string $start, ?string $end): array
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return self::zeros();
        }

        $orderIds = self::ordersInPeriodQuery($start, $end)->pluck('id');
        $taxasCobradas = self::sumMerchantFeesForOrderIds($orderIds);

        $custoVendas = 0.0;
        self::ordersInPeriodQuery($start, $end)
            ->select(['id', 'payment_method', 'metadata', 'gateway'])
            ->chunkById(500, function ($orders) use (&$custoVendas) {
                foreach ($orders as $order) {
                    if (self::paymentBucket($order) !== 'pix') {
                        continue;
                    }
                    $slug = trim((string) ($order->gateway ?? ''));
                    if ($slug === '') {
                        continue;
                    }
                    $custoVendas += GatewayPayoutEconomics::fromSlug($slug)['admin_fee_pix_brl'];
                }
            });

        $custoSaques = 0.0;
        if (Schema::hasTable('withdrawals')) {
            $wdQ = Withdrawal::query()->where('status', 'completed');
            if ($start && $end) {
                $wdQ->whereBetween('created_at', [$start, $end]);
            } elseif ($start) {
                $wdQ->where('created_at', '>=', $start);
            } elseif ($end) {
                $wdQ->where('created_at', '<=', $end);
            }
            $wdQ->select(['id', 'payout_provider'])->chunkById(500, function ($rows) use (&$custoSaques) {
                foreach ($rows as $w) {
                    $slug = $w->payout_provider;
                    if ($slug === null || $slug === '') {
                        $slug = PlatformPayoutGateway::activeSlug();
                    }
                    if ($slug === null || $slug === '') {
                        continue;
                    }
                    $custoSaques += GatewayPayoutEconomics::fromSlug($slug)['admin_fee_payout_brl'];
                }
            });
        }

        $taxasCobradas = round($taxasCobradas, 2);
        $custoVendas = round($custoVendas, 2);
        $custoSaques = round($custoSaques, 2);
        $liquido = round($taxasCobradas - $custoVendas - $custoSaques, 2);

        return [
            'faturamento_taxas_cobradas' => $taxasCobradas,
            'faturamento_custo_adquirente_vendas' => $custoVendas,
            'faturamento_custo_adquirente_saques' => $custoSaques,
            'faturamento_liquido' => $liquido,
        ];
    }

    /**
     * @return array{faturamento_taxas_cobradas: float, faturamento_custo_adquirente_vendas: float, faturamento_custo_adquirente_saques: float, faturamento_liquido: float}
     */
    private static function zeros(): array
    {
        return [
            'faturamento_taxas_cobradas' => 0.0,
            'faturamento_custo_adquirente_vendas' => 0.0,
            'faturamento_custo_adquirente_saques' => 0.0,
            'faturamento_liquido' => 0.0,
        ];
    }

    private static function ordersInPeriodQuery(?string $start, ?string $end): Builder
    {
        $q = Order::query()->where('status', 'completed');
        if ($start && $end) {
            $q->whereBetween('created_at', [$start, $end]);
        } elseif ($start) {
            $q->where('created_at', '>=', $start);
        } elseif ($end) {
            $q->where('created_at', '<=', $end);
        }

        return $q;
    }

    /**
     * Alinha ao listener de crédito: cartão/boleto vs PIX (default).
     */
    private static function paymentBucket(Order $order): string
    {
        $method = $order->payment_method;
        if ($method === null || $method === '') {
            $meta = $order->metadata ?? [];
            $method = is_array($meta) ? ($meta['checkout_payment_method'] ?? null) : null;
        }

        return match ($method) {
            'card' => 'card',
            'boleto' => 'boleto',
            'pix_auto', 'pix', null, '' => 'pix',
            default => 'pix',
        };
    }

    /**
     * Soma taxas sem duplicar: após liberação existem credit_sale_pending (com released_at) e credit_sale com o mesmo fee.
     *
     * @param  Collection<int, int>  $orderIds
     */
    private static function sumMerchantFeesForOrderIds(Collection $orderIds): float
    {
        if ($orderIds->isEmpty()) {
            return 0.0;
        }

        $txs = WalletTransaction::query()
            ->whereIn('order_id', $orderIds)
            ->whereIn('type', [WalletTransaction::TYPE_CREDIT_SALE, WalletTransaction::TYPE_CREDIT_SALE_PENDING])
            ->get(['order_id', 'type', 'amount_fee'])
            ->groupBy('order_id');

        $total = 0.0;
        foreach ($orderIds as $oid) {
            $group = $txs->get($oid, collect());
            if ($group->isEmpty()) {
                continue;
            }
            $hasReleasedSale = $group->contains(
                fn (WalletTransaction $t) => $t->type === WalletTransaction::TYPE_CREDIT_SALE
            );
            if ($hasReleasedSale) {
                $total += (float) $group
                    ->where('type', WalletTransaction::TYPE_CREDIT_SALE)
                    ->sum('amount_fee');
            } else {
                $total += (float) $group
                    ->where('type', WalletTransaction::TYPE_CREDIT_SALE_PENDING)
                    ->sum('amount_fee');
            }
        }

        return $total;
    }
}
