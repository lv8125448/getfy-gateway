<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TenantWallet;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Platform\PlatformRevenueKpis;
use App\Support\SqlDialect;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const PERIODS = ['hoje', 'ontem', '7dias', 'mes', 'ano', 'total'];

    public function __invoke(Request $request): Response
    {
        $period = $request->query('period', 'hoje');
        if (! in_array($period, self::PERIODS, true)) {
            $period = 'hoje';
        }

        [$start, $end] = $this->rangeForPeriod($period);

        $ordersBase = Order::query()->where('status', 'completed');
        if ($start && $end) {
            $ordersBase->whereBetween('created_at', [$start, $end]);
        } elseif ($start) {
            $ordersBase->where('created_at', '>=', $start);
        } elseif ($end) {
            $ordersBase->where('created_at', '<=', $end);
        }

        $vendasTotais = (float) (clone $ordersBase)->sum('amount');
        $quantidadeVendas = (clone $ordersBase)->count();
        $ticketMedio = $quantidadeVendas > 0 ? $vendasTotais / $quantidadeVendas : 0.0;

        $walletAvailable = 0.0;
        $walletPending = 0.0;
        $withdrawalsTotal = 0.0;
        $withdrawalsPending = 0.0;
        if (Schema::hasTable('tenant_wallets')) {
            $walletAvailable = (float) TenantWallet::query()->sum('available_balance');
            $walletPending = (float) TenantWallet::query()->sum('pending_balance');
        }
        if (Schema::hasTable('withdrawals')) {
            $withdrawalsTotal = (float) Withdrawal::query()
                ->when($start && $end, fn ($q) => $q->whereBetween('created_at', [$start, $end]))
                ->where('status', 'completed')
                ->sum('amount');

            $withdrawalsPending = (float) Withdrawal::query()->where('status', 'pending')->sum('amount');
        }

        $infoprodutoresCount = User::query()->where('role', User::ROLE_INFOPRODUTOR)->count();

        $grafico = $this->buildChart($period, $start, $end);

        $revenueKpis = PlatformRevenueKpis::compute($start, $end);

        $ultimasTransacoes = Order::query()
            ->with(['product:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'email' => $o->email,
                'product_name' => $o->product?->name,
                'amount' => (float) $o->amount,
                'status' => $o->status,
                'gateway' => $o->gateway,
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Platform/Dashboard', [
            'period' => $period,
            'kpis' => [
                'wallet_available' => round($walletAvailable, 2),
                'wallet_pending' => round($walletPending, 2),
                'vendas_totais' => round($vendasTotais, 2),
                'quantidade_vendas' => $quantidadeVendas,
                'ticket_medio' => round($ticketMedio, 2),
                'withdrawals_total' => round($withdrawalsTotal, 2),
                'withdrawals_pending' => round($withdrawalsPending, 2),
                'infoprodutores_count' => $infoprodutoresCount,
                'faturamento_taxas_cobradas' => $revenueKpis['faturamento_taxas_cobradas'],
                'faturamento_custo_adquirente_vendas' => $revenueKpis['faturamento_custo_adquirente_vendas'],
                'faturamento_custo_adquirente_saques' => $revenueKpis['faturamento_custo_adquirente_saques'],
                'faturamento_liquido' => $revenueKpis['faturamento_liquido'],
            ],
            'grafico_vendas' => $grafico,
            'ultimas_transacoes' => $ultimasTransacoes,
        ]);
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

    private function buildChart(string $period, ?string $start, ?string $end): array
    {
        $query = Order::query()->where('status', 'completed');
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
}
