<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import VendasTabs from '@/components/vendas/VendasTabs.vue';
import { Repeat, Users, TrendingUp } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const props = defineProps({
    stats: { type: Object, default: () => ({ ativas: 0, clientes: 0, mrr: 0 }) },
    assinaturas: { type: [Array, Object], default: () => [] },
});

const assinaturasList = computed(() => props.assinaturas?.data ?? (Array.isArray(props.assinaturas) ? props.assinaturas : []));

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);
}

function statusBadgeClass(status) {
    const map = {
        active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        past_due: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        cancelled: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700/50 dark:text-zinc-300',
    };
    return map[status] ?? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700/50 dark:text-zinc-300';
}

function statusBadgeLabel(status) {
    const map = {
        active: t('subscriptions.status.active', 'Ativa'),
        past_due: t('subscriptions.status.past_due', 'Em atraso'),
        cancelled: t('subscriptions.status.cancelled', 'Cancelada'),
    };
    return map[status] ?? status ?? '–';
}
</script>

<template>
    <div class="space-y-6">
        <VendasTabs />
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                        <Repeat class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ t('subscriptions.active', 'Assinaturas ativas') }}</p>
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ stats.ativas }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <Users class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ t('sales.customer', 'Clientes') }}</p>
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ stats.clientes }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <TrendingUp class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">MRR</p>
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ formatBRL(stats.mrr) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ t('sales.tab_subscriptions', 'Assinaturas') }}</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('subscriptions.list_hint', 'Lista de assinaturas ativas. Os lembretes de renovação são enviados por e-mail antes do vencimento.') }}
                </p>
            </div>

            <div v-if="assinaturasList.length > 0" class="sm:hidden p-4">
                <div class="space-y-3">
                    <div
                        v-for="s in assinaturasList"
                        :key="s.id"
                        class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold leading-snug text-zinc-900 dark:text-white">
                                    {{ s.user?.name || '—' }}
                                </p>
                                <p class="mt-0.5 break-words text-xs leading-snug text-zinc-500 dark:text-zinc-400">
                                    {{ s.user?.email || '—' }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    statusBadgeClass(s.status),
                                ]"
                            >
                                {{ statusBadgeLabel(s.status) }}
                            </span>
                        </div>

                        <div class="mt-4 rounded-lg bg-zinc-50/60 p-3 dark:bg-zinc-900/30">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ t('sidebar.products', 'Produto') }}
                                    </p>
                                    <p class="text-[11px] text-right font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ t('subscriptions.plan', 'Plano') }}
                                    </p>
                                    <p class="break-words text-sm font-medium leading-snug text-zinc-900 dark:text-white">
                                        {{ s.product?.name || '—' }}
                                    </p>
                                    <p class="break-words text-right text-sm font-medium leading-snug text-zinc-900 dark:text-white">
                                        {{ s.plan?.name || '—' }}
                                    </p>
                                    <p class="col-span-2 break-words text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ s.plan?.interval_label || s.plan?.interval || '—' }}
                                    </p>
                                </div>

                                <div class="flex items-end justify-between gap-3">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        {{ t('subscriptions.renews_on', 'Renova em') }}
                                    </p>
                                    <p class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">
                                        {{ s.current_period_end || '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="assinaturasList.length > 0" class="hidden overflow-x-auto sm:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50/80 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('sales.customer', 'Cliente') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('subscriptions.product_plan', 'Produto / Plano') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('subscriptions.renews_on', 'Renova em') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('sales.status', 'Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <tr v-for="s in assinaturasList" :key="s.id" class="text-zinc-700 dark:text-zinc-300">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ s.user?.name || '—' }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ s.user?.email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ s.product?.name || '—' }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ s.plan?.name }} · {{ s.plan?.interval_label || s.plan?.interval }}</p>
                            </td>
                            <td class="px-4 py-3">{{ s.current_period_end || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        statusBadgeClass(s.status),
                                    ]"
                                >
                                    {{ statusBadgeLabel(s.status) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav
                v-if="assinaturas?.links?.length > 3"
                class="flex items-center justify-center gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700"
                :aria-label="t('common.pagination', 'Paginação')"
            >
                <a
                    v-for="link in assinaturas.links"
                    :key="link.label"
                    :href="link.url"
                    :aria-current="link.active ? 'page' : undefined"
                    :aria-disabled="!link.url"
                    :class="[
                        'relative inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition',
                        link.active
                            ? 'z-10 bg-[var(--color-primary)] text-white'
                            : link.url
                              ? 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'
                              : 'cursor-not-allowed text-zinc-400 dark:text-zinc-500',
                    ]"
                    v-html="link.label"
                    @click.prevent="link.url && router.visit(link.url, { preserveState: true })"
                />
            </nav>
            <div v-else-if="assinaturasList.length === 0" class="p-8 text-center">
                <Repeat class="mx-auto h-14 w-14 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-3 font-medium text-zinc-600 dark:text-zinc-400">{{ t('subscriptions.empty', 'Nenhuma assinatura ainda') }}</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-500">
                    {{ t('subscriptions.empty_hint', 'Os produtos configurados como "Assinatura" com planos aparecerão aqui quando houver assinantes ativos.') }}
                </p>
            </div>
        </div>
    </div>
</template>
