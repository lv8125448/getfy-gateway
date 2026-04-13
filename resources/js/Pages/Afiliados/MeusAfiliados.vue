<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/composables/useI18n';
import { Search, Package, Ban, CheckCircle2, XCircle } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();
const page = usePage();

const props = defineProps({
    enrollments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    products_filter: { type: Array, default: () => [] },
});

const list = computed(() => props.enrollments?.data ?? []);
const links = computed(() => props.enrollments?.links ?? []);

const filterStatus = ref(props.filters?.status ?? 'all');
const filterProductId = ref(props.filters?.product_id ?? '');
const filterQ = ref(props.filters?.q ?? '');

watch(
    () => props.filters,
    (f) => {
        filterStatus.value = f?.status ?? 'all';
        filterProductId.value = f?.product_id ?? '';
        filterQ.value = f?.q ?? '';
    },
    { deep: true }
);

let searchDebounce;
watch(filterQ, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(applyFilters, 400);
});

function applyFilters() {
    router.get(
        '/afiliados',
        {
            status: filterStatus.value,
            product_id: filterProductId.value || undefined,
            q: (filterQ.value || '').trim() || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function onStatusChange() {
    applyFilters();
}

function onProductChange() {
    applyFilters();
}

const processingId = ref(null);

function postAction(url) {
    processingId.value = url;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
        },
    });
}

function statusClass(status) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    if (status === 'pending') return 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200';
    if (status === 'rejected') return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
    if (status === 'revoked') return 'bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200';
    return 'bg-zinc-100 text-zinc-700';
}

function statusLabel(status) {
    const map = {
        approved: t('affiliates.status_approved', 'Ativo'),
        pending: t('affiliates.status_pending', 'Pendente'),
        rejected: t('affiliates.status_rejected', 'Recusado'),
        revoked: t('affiliates.status_revoked', 'Revogado'),
    };
    return map[status] ?? status;
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ t('affiliates.my_affiliates_title', 'Meus Afiliados') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('affiliates.my_affiliates_subtitle', 'Gerencie solicitações e afiliações de todos os seus produtos.') }}
            </p>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ page.props.flash.error }}
        </div>

        <!-- Filtros -->
        <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/80 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="min-w-[160px] flex-1">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    {{ t('affiliates.filter_status', 'Situação') }}
                </label>
                <select
                    v-model="filterStatus"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                    @change="onStatusChange"
                >
                    <option value="all">{{ t('affiliates.filter_all', 'Todos') }}</option>
                    <option value="pending">{{ t('affiliates.filter_pending', 'Pendentes') }}</option>
                    <option value="approved">{{ t('affiliates.filter_active', 'Ativos') }}</option>
                    <option value="blocked">{{ t('affiliates.filter_blocked', 'Bloqueados (recusados/revogados)') }}</option>
                    <option value="rejected">{{ t('affiliates.filter_rejected', 'Recusados') }}</option>
                    <option value="revoked">{{ t('affiliates.filter_revoked', 'Revogados') }}</option>
                </select>
            </div>
            <div class="min-w-[180px] flex-1">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    {{ t('affiliates.filter_product', 'Produto') }}
                </label>
                <select
                    v-model="filterProductId"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                    @change="onProductChange"
                >
                    <option value="">{{ t('affiliates.all_products', 'Todos os produtos') }}</option>
                    <option v-for="p in products_filter" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div class="min-w-[200px] flex-[2]">
                <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    {{ t('affiliates.search_affiliate', 'Buscar afiliado') }}
                </label>
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" aria-hidden="true" />
                    <input
                        v-model="filterQ"
                        type="search"
                        class="w-full rounded-lg border border-zinc-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                        :placeholder="t('affiliates.search_placeholder', 'Nome ou e-mail')"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('affiliates.col_affiliate', 'Afiliado') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('affiliates.col_product', 'Produto') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('affiliates.col_status', 'Situação') }}
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('affiliates.col_actions', 'Ações') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <tr v-for="row in list" :key="row.id" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ row.affiliate?.name ?? '—' }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ row.affiliate?.email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-700"
                                    >
                                        <img
                                            v-if="row.product?.image_url"
                                            :src="row.product.image_url"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                        <Package v-else class="h-4 w-4 text-zinc-400" aria-hidden="true" />
                                    </div>
                                    <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ row.product?.name ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass(row.status)]">
                                    {{ statusLabel(row.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <template v-if="row.status === 'pending'">
                                        <Button
                                            type="button"
                                            size="sm"
                                            class="gap-1"
                                            :disabled="processingId !== null"
                                            @click="postAction(`/afiliados/enrollments/${row.id}/approve`)"
                                        >
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                            {{ t('affiliates.action_approve', 'Aprovar') }}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="gap-1"
                                            :disabled="processingId !== null"
                                            @click="postAction(`/afiliados/enrollments/${row.id}/reject`)"
                                        >
                                            <XCircle class="h-3.5 w-3.5" />
                                            {{ t('affiliates.action_reject', 'Recusar') }}
                                        </Button>
                                    </template>
                                    <Button
                                        v-if="row.status === 'approved'"
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="gap-1 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                        :disabled="processingId !== null"
                                        @click="postAction(`/afiliados/enrollments/${row.id}/revoke`)"
                                    >
                                        <Ban class="h-3.5 w-3.5" />
                                        {{ t('affiliates.action_revoke', 'Bloquear / revogar') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!list.length">
                            <td colspan="4" class="px-4 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ t('affiliates.empty', 'Nenhuma afiliação encontrada com os filtros atuais.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav v-if="links.length > 3" class="flex flex-wrap justify-center gap-1" aria-label="Paginação">
            <template v-for="(link, i) in links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'inline-flex min-w-[2.25rem] items-center justify-center rounded-lg px-3 py-1.5 text-sm',
                        link.active
                            ? 'bg-[var(--color-primary)] font-medium text-white'
                            : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200',
                    ]"
                >
                    <span v-html="link.label" />
                </Link>
                <span
                    v-else
                    class="inline-flex min-w-[2.25rem] cursor-default items-center justify-center rounded-lg px-3 py-1.5 text-sm text-zinc-400"
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
