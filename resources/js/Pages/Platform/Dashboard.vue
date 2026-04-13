<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import {
    Wallet,
    CircleDollarSign,
    Users,
    ArrowDownCircle,
    ShoppingCart,
    Eye,
    EyeOff,
    Receipt,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const props = defineProps({
    period: { type: String, default: 'hoje' },
    kpis: {
        type: Object,
        default: () => ({
            wallet_available: 0,
            wallet_pending: 0,
            vendas_totais: 0,
            quantidade_vendas: 0,
            ticket_medio: 0,
            withdrawals_total: 0,
            withdrawals_pending: 0,
            infoprodutores_count: 0,
            faturamento_taxas_cobradas: 0,
            faturamento_custo_adquirente_vendas: 0,
            faturamento_custo_adquirente_saques: 0,
            faturamento_liquido: 0,
        }),
    },
    grafico_vendas: { type: Array, default: () => [] },
    ultimas_transacoes: { type: Array, default: () => [] },
});

const valuesVisible = ref(true);
const isDarkMode = ref(false);

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
});

const periodOptions = [
    { value: 'hoje', label: 'Hoje' },
    { value: 'ontem', label: 'Ontem' },
    { value: '7dias', label: '7 dias' },
    { value: 'mes', label: 'Mês' },
    { value: 'ano', label: 'Ano' },
    { value: 'total', label: 'Total' },
];

function setPeriod(value) {
    router.get('/plataforma/dashboard', { period: value }, { preserveState: false });
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function displayCurrency(value) {
    return valuesVisible.value ? formatBRL(value) : '••••••';
}

const chartSeries = computed(() => [
    {
        name: 'Vendas',
        data: valuesVisible.value
            ? props.grafico_vendas.map((d) => d.total)
            : props.grafico_vendas.map(() => 0),
    },
]);

const chartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
        animations: { enabled: true, speed: 600 },
    },
    colors: ['var(--color-primary)'],
    dataLabels: {
        enabled: true,
        formatter: (v) => (valuesVisible.value ? formatBRL(v) : ''),
        style: { fontSize: '11px' },
        offsetY: -4,
    },
    stroke: { curve: 'smooth', width: 2.5 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 0.3,
            opacityFrom: 0.5,
            opacityTo: 0.08,
        },
    },
    markers: {
        size: 4,
        strokeWidth: 2,
        hover: { size: 6 },
    },
    xaxis: {
        categories: (props.period === 'hoje' || props.period === 'ontem')
            ? props.grafico_vendas.map((d) => `${Number(d.data)}h`)
            : props.grafico_vendas.map((d) => {
                const [y, m, day] = (d.data || '').split('-');
                return day && m ? `${day}/${m}` : d.data;
            }),
        labels: { style: { colors: '#71717a', fontSize: '12px' } },
        axisBorder: { show: true },
        crosshairs: { show: true },
    },
    yaxis: {
        labels: {
            style: { colors: '#71717a', fontSize: '12px' },
            formatter: (v) => formatBRL(v),
        },
    },
    grid: {
        borderColor: 'var(--chart-grid, #e4e4e7)',
        strokeDashArray: 4,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true } },
        padding: { top: 20, right: 10, bottom: 0, left: 0 },
    },
    tooltip: {
        theme: isDarkMode.value ? 'dark' : 'light',
        shared: true,
        intersect: false,
        y: { formatter: (v) => (valuesVisible.value ? formatBRL(v) : '••••••') },
        style: { fontSize: '13px' },
    },
}));
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Visão consolidada</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Todos os tenants · pedidos concluídos</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    :aria-label="valuesVisible ? 'Ocultar valores' : 'Mostrar valores'"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-500 dark:border-zinc-600 dark:bg-zinc-800"
                    @click="valuesVisible = !valuesVisible"
                >
                    <Eye v-if="valuesVisible" class="h-5 w-5" />
                    <EyeOff v-else class="h-5 w-5" />
                </button>
            </div>
        </div>

        <nav class="flex flex-wrap items-center gap-1" aria-label="Período">
            <button
                v-for="opt in periodOptions"
                :key="opt.value"
                type="button"
                :aria-current="period === opt.value ? 'true' : undefined"
                class="rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                :class="period === opt.value
                    ? 'bg-[var(--color-primary)] text-white'
                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'"
                @click="setPeriod(opt.value)"
            >
                {{ opt.label }}
            </button>
        </nav>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <Wallet class="h-4 w-4 text-[var(--color-primary)]" />
                    Saldo disponível (carteiras)
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ displayCurrency(kpis.wallet_available) }}
                </p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <CircleDollarSign class="h-4 w-4 text-[var(--color-primary)]" />
                    Vendas (período)
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ displayCurrency(kpis.vendas_totais) }}
                </p>
                <p class="mt-1 text-xs text-zinc-500">{{ kpis.quantidade_vendas }} pedidos · TM {{ displayCurrency(kpis.ticket_medio) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <Receipt class="h-4 w-4 text-[var(--color-primary)]" />
                    Faturamento (taxas líquidas)
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ displayCurrency(kpis.faturamento_liquido) }}
                </p>
                <p class="mt-1 text-xs leading-relaxed text-zinc-500">
                    Taxas {{ displayCurrency(kpis.faturamento_taxas_cobradas) }}
                    <span class="hidden sm:inline"> · </span>
                    <span class="block sm:inline">Adq. vendas {{ displayCurrency(kpis.faturamento_custo_adquirente_vendas) }}</span>
                    <span class="hidden sm:inline"> · </span>
                    <span class="block sm:inline">Adq. saques {{ displayCurrency(kpis.faturamento_custo_adquirente_saques) }}</span>
                </p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <ArrowDownCircle class="h-4 w-4 text-[var(--color-primary)]" />
                    Retiradas
                </div>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    Concluídas: <span class="font-semibold">{{ displayCurrency(kpis.withdrawals_total) }}</span>
                </p>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    Pendentes: <span class="font-semibold">{{ displayCurrency(kpis.withdrawals_pending) }}</span>
                </p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <Users class="h-4 w-4 text-[var(--color-primary)]" />
                    Infoprodutores
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">
                    {{ kpis.infoprodutores_count }}
                </p>
                <p class="mt-1 text-xs text-zinc-500">PIX pendente agregado: {{ displayCurrency(kpis.wallet_pending) }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
            <h3 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-white">Vendas no período</h3>
            <VueApexCharts type="area" height="280" :options="chartOptions" :series="chartSeries" />
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/60">
            <div class="flex items-center gap-2 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <ShoppingCart class="h-4 w-4 text-zinc-500" />
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Últimas transações</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-2">Data</th>
                            <th class="px-4 py-2">E-mail</th>
                            <th class="px-4 py-2">Produto</th>
                            <th class="px-4 py-2 text-right">Valor</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in ultimas_transacoes" :key="t.id" class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="whitespace-nowrap px-4 py-2 text-zinc-600 dark:text-zinc-300">
                                {{ t.created_at ? new Date(t.created_at).toLocaleString('pt-BR') : '—' }}
                            </td>
                            <td class="max-w-[180px] truncate px-4 py-2 text-zinc-700 dark:text-zinc-200">{{ t.email }}</td>
                            <td class="max-w-[200px] truncate px-4 py-2 text-zinc-600 dark:text-zinc-400">{{ t.product_name || '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium tabular-nums">{{ formatBRL(t.amount) }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs dark:bg-zinc-800">{{ t.status }}</span>
                            </td>
                        </tr>
                        <tr v-if="!ultimas_transacoes.length">
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">Nenhuma transação ainda.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
