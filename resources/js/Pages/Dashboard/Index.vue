<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'radix-vue';
import VueApexCharts from 'vue3-apexcharts';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import ConquistasWidget from '@/components/layout/ConquistasWidget.vue';
import { CircleDollarSign, ShoppingCart, CreditCard, ShoppingBag, RotateCcw, Package, Eye, EyeOff, ChevronDown, Check } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

defineOptions({ layout: LayoutInfoprodutor });

const page = usePage();
const hasAchievementsProgress = computed(() => !!(page.props.achievementsProgress ?? null));
const { t } = useI18n();

const valuesVisible = ref(true);
const isDarkMode = ref(false);

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
});

const props = defineProps({
    period: { type: String, default: 'hoje' },
    vendas_totais: { type: Number, default: 0 },
    vendas_pendentes: { type: Number, default: 0 },
    quantidade_vendas: { type: Number, default: 0 },
    ticket_medio: { type: Number, default: 0 },
    formas_pagamento: { type: Array, default: () => [] },
    taxa_conversao: { type: Number, default: 0 },
    abandono_carrinho: { type: Number, default: 0 },
    reembolsos_count: { type: Number, default: 0 },
    reembolsos_total: { type: Number, default: 0 },
    quantidade_produtos: { type: Number, default: 0 },
    grafico_vendas: { type: Array, default: () => [] },
});

const periodOptions = [
    { value: 'hoje', label: t('period.today', 'Hoje') },
    { value: 'ontem', label: t('period.yesterday', 'Ontem') },
    { value: '7dias', label: t('period.7days', '7 dias') },
    { value: 'mes', label: t('period.month', 'Mês') },
    { value: 'ano', label: t('period.year', 'Ano') },
    { value: 'total', label: t('period.total', 'Total') },
];

function setPeriod(value) {
    router.get('/dashboard', { period: value }, { preserveState: false });
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function displayCurrency(value) {
    return valuesVisible.value ? formatBRL(value) : '••••••';
}

function displayNumber(value) {
    return valuesVisible.value ? String(value) : '—';
}

const chartSeries = computed(() => [
    {
        name: t('sales.tab_sales', 'Vendas'),
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
        x: { format: props.period === 'hoje' || props.period === 'ontem' ? 'HH' : 'dd/MM/yyyy' },
        y: { formatter: (v) => (valuesVisible.value ? formatBRL(v) : '••••••') },
        style: { fontSize: '13px' },
    },
    crosshairs: {
        stroke: { width: 1, dashArray: 4 },
    },
}));
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ t('dashboard.title', 'Dashboard') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('dashboard.subtitle', 'Visão geral de desempenho, vendas e métricas do período.') }}
            </p>
        </div>

        <!-- Barrinha de progresso (Conquistas) - só no mobile -->
        <div v-if="hasAchievementsProgress" class="lg:hidden">
            <ConquistasWidget variant="dashboard" />
        </div>

        <!-- Barra de período + olho -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Mobile: dropdown + olho ao lado -->
            <div class="flex items-center gap-2 lg:hidden">
                <SelectRoot :model-value="period" @update:model-value="setPeriod">
                    <SelectTrigger
                        type="button"
                        :aria-label="t('dashboard.period', 'Período')"
                        class="flex h-10 w-[240px] shrink-0 cursor-pointer items-center justify-between gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2 text-left text-sm transition hover:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:ring-offset-0 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:hover:border-zinc-500"
                    >
                        <SelectValue :placeholder="t('dashboard.period', 'Período')" />
                        <ChevronDown class="h-4 w-4 shrink-0 text-zinc-400 dark:text-zinc-500" aria-hidden="true" />
                    </SelectTrigger>
                    <SelectPortal to="body">
                        <SelectContent
                            class="z-[9999] min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-xl border border-zinc-200 bg-white py-1 shadow-xl dark:border-zinc-600 dark:bg-zinc-800"
                            :side-offset="4"
                            position="popper"
                            :avoid-collisions="true"
                        >
                            <SelectViewport class="p-1">
                                <SelectItem
                                    v-for="opt in periodOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                    class="relative flex cursor-pointer select-none items-center rounded-lg py-2.5 pl-10 pr-4 text-sm outline-none transition data-[highlighted]:bg-[var(--color-primary)]/10 data-[highlighted]:text-[var(--color-primary)] data-[state=checked]:bg-[var(--color-primary)]/10 data-[state=checked]:text-[var(--color-primary)] dark:data-[highlighted]:bg-[var(--color-primary)]/20 dark:data-[state=checked]:bg-[var(--color-primary)]/20"
                                >
                                    <SelectItemIndicator class="absolute left-3 flex h-4 w-4 items-center justify-center">
                                        <Check class="h-4 w-4 text-[var(--color-primary)]" />
                                    </SelectItemIndicator>
                                    <SelectItemText>{{ opt.label }}</SelectItemText>
                                </SelectItem>
                            </SelectViewport>
                        </SelectContent>
                    </SelectPortal>
                </SelectRoot>
                <button
                    type="button"
                    :aria-label="valuesVisible ? t('dashboard.hide_values', 'Ocultar valores') : t('dashboard.show_values', 'Mostrar valores')"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-500 transition-colors hover:bg-zinc-50 hover:text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                    @click="valuesVisible = !valuesVisible"
                >
                    <Eye v-if="valuesVisible" class="h-5 w-5" aria-hidden="true" />
                    <EyeOff v-else class="h-5 w-5" aria-hidden="true" />
                </button>
            </div>
            <!-- Desktop: abas -->
            <nav class="hidden flex-wrap items-center gap-1 lg:flex" :aria-label="t('dashboard.period', 'Período')">
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
            <!-- Desktop: olho -->
            <button
                type="button"
                :aria-label="valuesVisible ? t('dashboard.hide_values', 'Ocultar valores') : t('dashboard.show_values', 'Mostrar valores')"
                class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 lg:flex"
                @click="valuesVisible = !valuesVisible"
            >
                <Eye v-if="valuesVisible" class="h-5 w-5" aria-hidden="true" />
                <EyeOff v-else class="h-5 w-5" aria-hidden="true" />
            </button>
        </div>

        <!-- Cards de destaque -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div
                class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50"
            >
                <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                    <CircleDollarSign class="h-5 w-5" />
                    <span class="text-sm font-medium">{{ t('dashboard.total_sales', 'Vendas totais') }}</span>
                </div>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">
                    {{ displayCurrency(vendas_totais) }}
                </p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('dashboard.pending_sales', 'Vendas pendentes') }}: {{ displayCurrency(vendas_pendentes) }}
                </p>
            </div>
            <div
                class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50"
            >
                <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                    <ShoppingCart class="h-5 w-5" />
                    <span class="text-sm font-medium">{{ t('dashboard.sales_count', 'Quantidade de vendas') }}</span>
                </div>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">
                    {{ displayNumber(quantidade_vendas) }}
                </p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('dashboard.avg_ticket', 'Ticket médio') }}: {{ displayCurrency(ticket_medio) }}
                </p>
            </div>
        </div>

        <!-- Formas de pagamento + lateral -->
        <div class="grid gap-4 lg:grid-cols-3">
            <div
                class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50 lg:col-span-2"
            >
                <h2 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                    <CreditCard class="h-4 w-4 text-zinc-500" />
                    {{ t('dashboard.payment_methods', 'Formas de pagamento') }}
                </h2>
                <ul class="mt-4 space-y-3">
                    <li
                        v-for="fp in formas_pagamento"
                        :key="fp.metodo"
                        class="flex items-center justify-between border-b border-zinc-200/60 py-2 last:border-0 dark:border-zinc-700/60"
                    >
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ fp.label }}</span>
                        <span class="text-sm font-medium text-zinc-900 dark:text-white">
                            {{ displayCurrency(fp.total) }}
                            <span class="font-normal text-zinc-500">({{ displayNumber(fp.quantidade) }})</span>
                        </span>
                    </li>
                    <li v-if="!formas_pagamento.length" class="py-4 text-center text-sm text-zinc-500">
                        {{ t('dashboard.no_payments', 'Nenhum pagamento no período') }}
                    </li>
                </ul>
                <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('dashboard.conversion_rate', 'Taxa de conversão geral') }}</p>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white">
                        {{ valuesVisible ? `${taxa_conversao}%` : '—' }}
                    </p>
                </div>
            </div>
            <div class="space-y-4">
                <div
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50"
                >
                    <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                        <ShoppingBag class="h-4 w-4" />
                        <span class="text-sm font-medium">{{ t('dashboard.cart_abandonment', 'Abandono de carrinho') }}</span>
                    </div>
                    <p class="mt-2 text-lg font-bold text-zinc-900 dark:text-white">
                        {{ displayNumber(abandono_carrinho) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50"
                >
                    <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                        <RotateCcw class="h-4 w-4" />
                        <span class="text-sm font-medium">{{ t('dashboard.refunds', 'Reembolso') }}</span>
                    </div>
                    <p class="mt-2 text-lg font-bold text-zinc-900 dark:text-white">
                        {{ displayCurrency(reembolsos_total) }}
                    </p>
                    <p class="text-xs text-zinc-500">{{ displayNumber(reembolsos_count) }} {{ t('dashboard.orders_count', 'pedido(s)') }}</p>
                </div>
                <div
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50"
                >
                    <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                        <Package class="h-4 w-4" />
                        <span class="text-sm font-medium">{{ t('dashboard.products', 'Produtos') }}</span>
                    </div>
                    <p class="mt-2 text-lg font-bold text-zinc-900 dark:text-white">
                        {{ displayNumber(quantidade_produtos) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Gráfico de vendas -->
        <div
            class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50"
        >
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ t('dashboard.sales_performance', 'Desempenho de vendas') }}</h2>
            <div class="mt-4 min-h-[280px]">
                <VueApexCharts
                    v-if="grafico_vendas.length"
                    type="area"
                    height="280"
                    :options="chartOptions"
                    :series="chartSeries"
                />
                <p
                    v-else
                    class="flex h-[280px] items-center justify-center text-sm text-zinc-500 dark:text-zinc-400"
                >
                    {{ t('dashboard.no_sales_data', 'Nenhum dado de vendas no período') }}
                </p>
            </div>
        </div>
    </div>
</template>
