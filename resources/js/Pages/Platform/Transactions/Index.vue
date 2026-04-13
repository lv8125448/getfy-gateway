<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import VendaDetailSidebar from '@/components/vendas/VendaDetailSidebar.vue';
import Button from '@/components/ui/Button.vue';
import {
    MoreVertical,
    FileText,
    CheckCircle,
    Ban,
    RotateCcw,
    AlertTriangle,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const page = usePage();

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: 'all', q: '' }),
    },
});

const filterStatus = ref(props.filters?.status ?? 'all');
const filterQ = ref(props.filters?.q ?? '');

const sidebarOpen = ref(false);
const selectedVenda = ref(null);

const openMenuId = ref(null);
const menuAnchorEl = ref(null);
const menuEl = ref(null);
const menuPos = ref({ top: 0, left: 0 });

watch(
    () => props.filters,
    (f) => {
        filterStatus.value = f?.status ?? 'all';
        filterQ.value = f?.q ?? '';
    },
    { deep: true }
);

const filterChips = [
    { status: 'all', label: 'Todos' },
    { status: 'pending', label: 'Pendente' },
    { status: 'completed', label: 'Aprovado' },
    { status: 'disputed', label: 'MED' },
    { status: 'refunded', label: 'Reembolsado' },
    { status: 'cancelled', label: 'Cancelado' },
];

function chipIsActive(chip) {
    return filterStatus.value === chip.status;
}

function selectChip(chip) {
    filterStatus.value = chip.status;
    router.get(
        '/plataforma/transacoes',
        {
            status: chip.status,
            q: filterQ.value?.trim() || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
}

function statusLabel(status) {
    const map = {
        completed: 'Aprovado',
        pending: 'Pendente',
        disputed: 'MED',
        cancelled: 'Cancelado',
        refunded: 'Reembolsado',
    };
    return map[status] ?? status ?? '—';
}

function statusBadgeClass(status) {
    if (status === 'completed') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    if (status === 'pending') return 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-100';
    if (status === 'disputed') return 'bg-orange-100 text-orange-900 dark:bg-orange-900/30 dark:text-orange-100';
    if (status === 'cancelled' || status === 'refunded') return 'bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200';
    return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200';
}

function applyFilters() {
    router.get(
        '/plataforma/transacoes',
        {
            status: filterStatus.value,
            q: filterQ.value?.trim() || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function approveQuerySuffix() {
    const params = new URLSearchParams();
    if (filterStatus.value && filterStatus.value !== 'all') params.set('status', filterStatus.value);
    if (filterQ.value?.trim()) params.set('q', filterQ.value.trim());
    const s = params.toString();
    return s ? `?${s}` : '';
}

/** action: aprovar-manualmente | cancelar | reembolsar | marcar-med */
function orderActionUrl(action, id) {
    return `/plataforma/transacoes/pedidos/${id}/${action}${approveQuerySuffix()}`;
}

function approvePendingOrder(id) {
    if (!confirm('Aprovar este pedido como pago? O infoprodutor será creditado na carteira e o cliente receberá acesso conforme o produto.')) return;
    router.post(orderActionUrl('aprovar-manualmente', id), {}, { preserveScroll: true });
}

function confirmCancel(id) {
    if (!confirm('Cancelar este pedido pendente? O cliente não será cobrado (se ainda estiver pendente).')) return;
    router.post(orderActionUrl('cancelar', id), {}, { preserveScroll: true });
}

function confirmRefund(id) {
    if (
        !confirm(
            'Marcar como reembolsado? Se existir crédito de venda na carteira do infoprodutor, o valor líquido será debitado.'
        )
    )
        return;
    router.post(orderActionUrl('reembolsar', id), {}, { preserveScroll: true });
}

function confirmMed(id, wasPaid) {
    const msg = wasPaid
        ? 'Marcar como MED (contestação)? O valor líquido já creditado na carteira do infoprodutor será movido do saldo disponível para o saldo pendente (bloqueado) até a resolução.'
        : 'Marcar este pedido como MED (contestação)?';
    if (!confirm(msg)) return;
    router.post(orderActionUrl('marcar-med', id), {}, { preserveScroll: true });
}

function openDetail(v) {
    selectedVenda.value = v;
    sidebarOpen.value = true;
}

function closeSidebar() {
    sidebarOpen.value = false;
    selectedVenda.value = null;
}

const rows = () => props.orders?.data ?? [];

const menuOrder = computed(() => {
    if (openMenuId.value == null) return null;
    const list = rows();
    return list.find((x) => x.id === openMenuId.value) ?? null;
});

function hasActionsMenu(o) {
    return (
        o.status === 'pending' || o.status === 'completed' || o.status === 'disputed'
    );
}

async function updateMenuPosition() {
    const anchor = menuAnchorEl.value;
    if (!anchor || openMenuId.value == null) return;

    const rect = anchor.getBoundingClientRect();
    const minMargin = 8;
    const desiredWidth = 220;
    const viewportW = window.innerWidth || 0;
    const viewportH = window.innerHeight || 0;

    let left = rect.right - desiredWidth;
    left = Math.max(minMargin, Math.min(left, Math.max(minMargin, viewportW - desiredWidth - minMargin)));

    let top = rect.bottom + 4;
    top = Math.max(minMargin, Math.min(top, Math.max(minMargin, viewportH - minMargin)));

    menuPos.value = { top, left };

    await nextTick();
    const menu = menuEl.value;
    if (!menu) return;

    const menuRect = menu.getBoundingClientRect();
    const spaceBelow = viewportH - rect.bottom;
    const spaceAbove = rect.top;
    const shouldOpenUp = menuRect.height + 8 > spaceBelow && spaceAbove >= menuRect.height + 8;

    if (shouldOpenUp) {
        const newTop = Math.max(minMargin, rect.top - menuRect.height - 4);
        menuPos.value = { top: newTop, left: menuPos.value.left };
    }
}

async function toggleMenu(id, event) {
    if (openMenuId.value === id) {
        closeMenu();
        return;
    }
    openMenuId.value = id;
    menuAnchorEl.value = event?.currentTarget ?? null;
    await nextTick();
    await updateMenuPosition();
}

function closeMenu() {
    openMenuId.value = null;
    menuAnchorEl.value = null;
}

function handleClickOutside(event) {
    if (openMenuId.value == null) return;
    const wrap = document.querySelector(`[data-tx-menu="${openMenuId.value}"]`);
    const menu = menuEl.value;
    if (wrap && wrap.contains(event.target)) return;
    if (menu && menu.contains(event.target)) return;
    closeMenu();
}

/**
 * Captura o pedido antes de fechar o menu — senão `menuOrder` fica null e o sidebar/ações quebram.
 *
 * @param {function(row: object): void} action
 */
function runMenuAction(action) {
    const row = menuOrder.value;
    if (!row) return;
    closeMenu();
    action(row);
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    window.addEventListener('resize', updateMenuPosition);
    window.addEventListener('scroll', updateMenuPosition, true);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    window.removeEventListener('resize', updateMenuPosition);
    window.removeEventListener('scroll', updateMenuPosition, true);
});

const paginationLinks = computed(() => props.orders?.links ?? []);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Transações</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Pedidos da plataforma por status (Aprovado, MED, Reembolsado, etc.). Saques estão em
                <a href="/plataforma/saques" class="font-medium text-[var(--color-primary)] underline-offset-2 hover:underline"
                    >Saques</a
                >. Aprovação manual apenas para pedidos pendentes.
            </p>
        </div>

        <p
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </p>
        <p
            v-if="page.props.flash?.error"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ page.props.flash.error }}
        </p>

        <div
            class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div class="w-full overflow-x-auto [-webkit-overflow-scrolling:touch]">
                <div
                    class="inline-flex min-w-full flex-wrap gap-2"
                    role="tablist"
                    aria-label="Filtros de transações"
                >
                    <button
                        v-for="chip in filterChips"
                        :key="chip.status"
                        type="button"
                        role="tab"
                        :aria-selected="chipIsActive(chip)"
                        :class="[
                            'inline-flex items-center gap-2 whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-medium transition',
                            chipIsActive(chip)
                                ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] dark:bg-[var(--color-primary)]/20'
                                : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900/50 dark:text-zinc-200',
                        ]"
                        @click="selectChip(chip)"
                    >
                        {{ chip.label }}
                    </button>
                </div>
            </div>

            <form
                class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                @submit.prevent="applyFilters"
            >
                <div class="min-w-[220px] flex-[2]">
                    <label for="tx-q" class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Buscar (e-mail, nome, ID do pedido)
                    </label>
                    <input
                        id="tx-q"
                        v-model="filterQ"
                        type="search"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        placeholder="Ex.: cliente@email.com ou 12345"
                        autocomplete="off"
                    />
                </div>
                <Button type="submit">Buscar</Button>
            </form>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500 dark:border-zinc-600 dark:bg-zinc-900/50">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Pedido</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Infoprodutor</th>
                            <th class="px-4 py-3">Produto</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Método</th>
                            <th class="px-4 py-3 text-right">Valor (bruto)</th>
                            <th class="relative w-14 px-2 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        <tr
                            v-for="o in rows()"
                            :key="o.id"
                            class="cursor-pointer bg-white transition hover:bg-zinc-50 dark:bg-zinc-800/40 dark:hover:bg-zinc-700/50"
                            tabindex="0"
                            role="button"
                            @click="openDetail(o)"
                            @keydown.enter.prevent="openDetail(o)"
                            @keydown.space.prevent="openDetail(o)"
                        >
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                {{ o.created_at ? new Date(o.created_at).toLocaleString('pt-BR') : '—' }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-zinc-700 dark:text-zinc-200">#{{ o.id }}</td>
                            <td class="max-w-[200px] px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ o.customer_name }}</div>
                                <div class="truncate text-xs text-zinc-500">{{ o.customer_email }}</div>
                            </td>
                            <td class="max-w-[200px] px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ o.infoprodutor_name }}</div>
                                <div class="truncate text-xs text-zinc-500">{{ o.infoprodutor_email ?? '—' }}</div>
                            </td>
                            <td class="max-w-[220px] px-4 py-3">
                                <span class="line-clamp-2">{{ o.product_label }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <span
                                        :class="['inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium', statusBadgeClass(o.status)]"
                                    >
                                        {{ statusLabel(o.status) }}
                                    </span>
                                    <span
                                        v-if="o.approved_manually"
                                        class="text-[10px] font-medium uppercase tracking-wide text-violet-600 dark:text-violet-400"
                                    >
                                        Aprovação manual
                                    </span>
                                </div>
                            </td>
                            <td class="max-w-[140px] px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                <span class="line-clamp-2">{{ o.payment_method_label }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-medium text-zinc-900 dark:text-white">
                                {{ formatBRL(o.amount_gross) }}
                            </td>
                            <td class="relative px-2 py-2 text-right align-middle" @click.stop>
                                <div
                                    v-if="hasActionsMenu(o)"
                                    class="relative flex justify-end"
                                    :data-tx-menu="o.id"
                                >
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                        :aria-expanded="openMenuId === o.id"
                                        aria-label="Abrir ações"
                                        @click="toggleMenu(o.id, $event)"
                                    >
                                        <MoreVertical class="h-4 w-4 shrink-0" />
                                    </button>
                                </div>
                                <span v-else class="inline-block py-1 text-xs text-zinc-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="!rows().length">
                            <td colspan="9" class="px-4 py-12 text-center text-zinc-500">Nenhuma transação encontrada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav
            v-if="paginationLinks.length > 3"
            class="flex flex-wrap items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in paginationLinks"
                :key="link.label + String(link.url)"
                :href="link.url || undefined"
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="!link.url"
                :class="[
                    'relative inline-flex min-h-[2.25rem] items-center rounded-lg px-3 py-2 text-sm font-medium transition',
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

        <VendaDetailSidebar
            :key="selectedVenda?.id ?? 'none'"
            :open="sidebarOpen"
            :venda="selectedVenda"
            @close="closeSidebar"
        />

        <Teleport to="body">
            <div
                v-if="openMenuId != null && menuOrder"
                ref="menuEl"
                class="fixed z-[100000] w-[220px] rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                :style="{ top: `${menuPos.top}px`, left: `${menuPos.left}px` }"
                role="menu"
                aria-label="Ações do pedido"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                    @click="runMenuAction((r) => openDetail(r))"
                >
                    <FileText class="h-4 w-4 shrink-0" />
                    Detalhes
                </button>
                <template v-if="menuOrder.status === 'pending'">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                        @click="runMenuAction((r) => approvePendingOrder(r.id))"
                    >
                        <CheckCircle class="h-4 w-4 shrink-0" />
                        Aprovar
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="runMenuAction((r) => confirmCancel(r.id))"
                    >
                        <Ban class="h-4 w-4 shrink-0" />
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="runMenuAction((r) => confirmMed(r.id, false))"
                    >
                        <AlertTriangle class="h-4 w-4 shrink-0" />
                        Marcar MED
                    </button>
                </template>
                <template v-else-if="menuOrder.status === 'completed'">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30"
                        @click="runMenuAction((r) => confirmRefund(r.id))"
                    >
                        <RotateCcw class="h-4 w-4 shrink-0" />
                        Reembolsar
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        @click="runMenuAction((r) => confirmMed(r.id, true))"
                    >
                        <AlertTriangle class="h-4 w-4 shrink-0" />
                        Marcar MED
                    </button>
                </template>
                <template v-else-if="menuOrder.status === 'disputed'">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30"
                        @click="runMenuAction((r) => confirmRefund(r.id))"
                    >
                        <RotateCcw class="h-4 w-4 shrink-0" />
                        Reembolsar
                    </button>
                </template>
            </div>
        </Teleport>
    </div>
</template>
