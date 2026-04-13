<?php

namespace App\Http\Controllers;

use App\Events\DashboardLoading;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\SqlDialect;
use Carbon\Carbon;
use App\Services\TeamAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const PERIODS = ['hoje', 'ontem', '7dias', 'mes', 'ano', 'total'];

    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __invoke(Request $request): Response
    {
        $period = $request->query('period', 'hoje');
        if (! in_array($period, self::PERIODS, true)) {
            $period = 'hoje';
        }

        $tenantId = auth()->user()->tenant_id;
        $cacheKey = 'dashboard:v2:' . ($tenantId ?? 'global') . ':' . $period;

        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId, $period) {
            [$start, $end] = $this->rangeForPeriod($period);

            $ordersQuery = Order::forTenant($tenantId);
            if (auth()->user()?->isTeam()) {
                $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
                $ordersQuery->whereIn('product_id', $allowed ?: ['__none__']);
            }
        if ($start && $end) {
            $ordersQuery->whereBetween('created_at', [$start, $end]);
        } elseif ($start) {
            $ordersQuery->where('created_at', '>=', $start);
        } elseif ($end) {
            $ordersQuery->where('created_at', '<=', $end);
        }

        $ordersCompleted = (clone $ordersQuery)->where('status', 'completed');
        $ordersPending = (clone $ordersQuery)->where('status', 'pending');
        $ordersRefunded = (clone $ordersQuery)->where('status', 'refunded');

        $vendasTotais = (float) $ordersCompleted->sum('amount');
        $quantidadeVendas = $ordersCompleted->count();
        $ticketMedio = $quantidadeVendas > 0 ? $vendasTotais / $quantidadeVendas : 0.0;
        $vendasPendentes = (float) $ordersPending->sum('amount');
        $reembolsosCount = $ordersRefunded->count();
        $reembolsosTotal = (float) (clone $ordersQuery)->where('status', 'refunded')->sum('amount');

        $formasPagamentoRows = (clone $ordersQuery)
            ->where('status', 'completed')
            ->select(['payment_method', 'metadata', 'gateway', 'amount'])
            ->get();

        $formasPagamento = $formasPagamentoRows
            ->groupBy(fn (Order $o) => $this->resolvePaymentMethodKey($o))
            ->map(function ($rows, $method) {
                return [
                    'metodo' => $method,
                    'label' => $this->paymentMethodLabel($method),
                    'total' => (float) $rows->sum(fn (Order $o) => (float) $o->amount),
                    'quantidade' => (int) $rows->count(),
                    '_sort' => $this->paymentMethodSort($method),
                ];
            })
            ->sortBy('_sort')
            ->map(function (array $row) {
                unset($row['_sort']);
                return $row;
            })
            ->values()
            ->all();

        $graficoVendas = $this->buildGraficoVendas($tenantId, $period, $start, $end);

        $productsQuery = Product::forTenant($tenantId);
        if (auth()->user()?->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            $productsQuery->whereIn('id', $allowed ?: ['__none__']);
        }
        $quantidadeProdutos = $productsQuery->count();

            return [
                'period' => $period,
                'vendas_totais' => round($vendasTotais, 2),
                'vendas_pendentes' => round($vendasPendentes, 2),
                'quantidade_vendas' => $quantidadeVendas,
                'ticket_medio' => round($ticketMedio, 2),
                'formas_pagamento' => $formasPagamento,
                'taxa_conversao' => 0,
                'abandono_carrinho' => 0,
                'reembolsos_count' => $reembolsosCount,
                'reembolsos_total' => round($reembolsosTotal, 2),
                'quantidade_produtos' => $quantidadeProdutos,
                'grafico_vendas' => $graficoVendas,
            ];
        });

        $data = new \ArrayObject($payload);
        $data['dashboard_banners'] = $this->dashboardBanners();
        event(new DashboardLoading($data));

        return Inertia::render('Dashboard/Index', $data->getArrayCopy());
    }

    private function rangeForPeriod(string $period): array
    {
        $now = Carbon::now();
        $start = null;
        $end = null;

        switch ($period) {
            case 'hoje':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'ontem':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                break;
            case '7dias':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'mes':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'ano':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'total':
                break;
        }

        return [$start?->toDateTimeString(), $end?->toDateTimeString()];
    }

    private function resolvePaymentMethodKey(Order $order): string
    {
        $meta = is_array($order->metadata) ? $order->metadata : [];
        $method = strtolower(trim((string) ($meta['checkout_payment_method'] ?? $order->payment_method ?? '')));
        if ($method === 'pix_auto') {
            $method = 'pix';
        }
        if (in_array($method, ['spacepag', 'woovi', 'pushinpay', 'cajupay', 'efi'], true)) {
            $method = 'pix';
        }
        if (in_array($method, ['pix', 'card', 'boleto'], true)) {
            return $method;
        }

        $gateway = strtolower(trim((string) ($order->gateway ?? '')));
        if ($gateway === '') {
            return 'outro';
        }
        if (str_contains($gateway, 'pix') || in_array($gateway, ['spacepag', 'woovi', 'pushinpay', 'cajupay', 'efi'], true)) {
            return 'pix';
        }
        if ($gateway === 'card' || str_contains($gateway, 'cartao') || str_contains($gateway, 'cartão') || str_contains($gateway, 'credito')) {
            return 'card';
        }
        if ($gateway === 'boleto' || str_contains($gateway, 'boleto')) {
            return 'boleto';
        }

        return 'outro';
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'pix' => 'PIX',
            'card' => 'Cartão',
            'boleto' => 'Boleto',
            default => 'Outro',
        };
    }

    private function paymentMethodSort(string $method): int
    {
        return match ($method) {
            'pix' => 1,
            'card' => 2,
            'boleto' => 3,
            default => 99,
        };
    }

    private function buildGraficoVendas(?int $tenantId, string $period, ?string $start, ?string $end): array
    {
        $query = Order::forTenant($tenantId)->where('status', 'completed');
        if (auth()->user()?->isTeam()) {
            $allowed = app(TeamAccessService::class)->allowedProductIdsFor(auth()->user());
            $query->whereIn('product_id', $allowed ?: ['__none__']);
        }

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        } elseif ($start) {
            $query->where('created_at', '>=', $start);
        } elseif ($end) {
            $query->where('created_at', '<=', $end);
        }

        $isHourly = in_array($period, ['hoje', 'ontem'], true);

        if ($isHourly) {
            $hour = SqlDialect::hourExpression('created_at');
            $rows = $query
                ->selectRaw($hour.' as hora, SUM(amount) as total')
                ->groupBy('hora')
                ->orderBy('hora')
                ->get()
                ->keyBy('hora');

            $result = [];
            for ($h = 0; $h <= 23; $h++) {
                $result[] = [
                    'data' => (string) $h,
                    'total' => (float) ($rows->get($h)?->total ?? 0),
                ];
            }

            return $result;
        }

        $dateExpr = SqlDialect::dateExpression('created_at');
        $rows = $query
            ->selectRaw($dateExpr.' as data, SUM(amount) as total')
            ->groupBy('data')
            ->orderBy('data')
            ->get();

        return $rows->map(fn ($r) => [
            'data' => $r->data,
            'total' => (float) $r->total,
        ])->values()->all();
    }

    private function dashboardBanners(): array
    {
        $raw = Setting::get('dashboard_banners', [], null);
        $rows = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int $idx) {
                return [
                    'id' => (string) ($item['id'] ?? ('banner-'.$idx)),
                    'title' => (string) ($item['title'] ?? ''),
                    'desktop_url' => (string) ($item['desktop_url'] ?? ''),
                    'mobile_url' => (string) ($item['mobile_url'] ?? ''),
                    'active' => (bool) ($item['active'] ?? true),
                    'sort_order' => (int) ($item['sort_order'] ?? ($idx + 1)),
                ];
            })
            ->filter(fn (array $item) => $item['active'] && ($item['desktop_url'] !== '' || $item['mobile_url'] !== ''))
            ->sortBy('sort_order')
            ->values()
            ->all();
    }
}
