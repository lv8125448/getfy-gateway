<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import CupomSidebar from '@/components/produtos/CupomSidebar.vue';
import { Pencil, Trash2, Ticket } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const props = defineProps({
    cupons: { type: Array, default: () => [] },
    produtos: { type: Array, default: () => [] },
});

const sidebarOpen = ref(false);
const couponToEdit = ref(null);
const couponToDelete = ref(null);

function openNew() {
    couponToEdit.value = null;
    sidebarOpen.value = true;
}

function openEdit(c) {
    couponToEdit.value = c;
    sidebarOpen.value = true;
}

function closeSidebar() {
    sidebarOpen.value = false;
    couponToEdit.value = null;
}

function openDeleteModal(c) {
    couponToDelete.value = c;
}

function closeDeleteModal() {
    couponToDelete.value = null;
}

function confirmDestroy() {
    const c = couponToDelete.value;
    if (!c) return;
    router.delete(`/produtos/cupons/${c.id}`, { preserveScroll: true });
    closeDeleteModal();
}

function formatValor(c) {
    if (c.type === 'percent') return `${Number(c.value)}%`;
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(c.value ?? 0);
}

function formatDate(str) {
    if (!str) return '—';
    const d = new Date(str);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function usosText(c) {
    if (c.max_uses == null) return `${c.used_count} ${t('coupons.uses', 'usos')}`;
    return `${c.used_count} / ${c.max_uses}`;
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex justify-end">
            <Button @click="openNew">
                {{ t('coupons.new', 'Novo cupom') }}
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('coupons.code', 'Código') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('coupons.type', 'Tipo') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('coupons.value', 'Valor') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('sidebar.products', 'Produto') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('coupons.uses', 'Usos') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('coupons.validity', 'Validade') }}
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                {{ t('common.active', 'Ativo') }}
                            </th>
                            <th scope="col" class="relative px-4 py-3">
                                <span class="sr-only">Ações</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <tr
                            v-for="c in cupons"
                            :key="c.id"
                            class="bg-white dark:bg-zinc-800"
                        >
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-zinc-900 dark:text-white">
                                {{ c.code }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ c.type === 'percent' ? t('coupons.type_percent', 'Percentual') : t('coupons.type_fixed', 'Fixo') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-900 dark:text-white">
                                {{ formatValor(c) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ c.product_name ?? t('common.all', 'Todos') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ usosText(c) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ formatDate(c.valid_from) }} – {{ formatDate(c.valid_until) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    :class="[
                                        'inline-block rounded px-2 py-0.5 text-xs font-medium',
                                        c.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                            : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400',
                                    ]"
                                >
                                    {{ c.is_active ? t('common.yes', 'Sim') : t('common.no', 'Não') }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                        :aria-label="t('coupons.edit', 'Editar cupom')"
                                        @click="openEdit(c)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-zinc-500 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        :aria-label="t('coupons.delete', 'Excluir cupom')"
                                        @click="openDeleteModal(c)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="!cupons.length"
                class="flex flex-col items-center justify-center py-16"
            >
                <Ticket class="h-14 w-14 text-zinc-400 dark:text-zinc-500" />
                <p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ t('coupons.empty', 'Nenhum cupom ainda.') }}</p>
                <Button class="mt-4" @click="openNew">
                    {{ t('coupons.create_first', 'Criar primeiro cupom') }}
                </Button>
            </div>
        </div>
    </div>

    <!-- Modal de confirmação de exclusão -->
    <Teleport to="body">
        <div
            v-if="couponToDelete"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-cupom-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="closeDeleteModal"
            />
            <div
                class="relative w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <h2 id="delete-cupom-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ t('coupons.delete_title', 'Excluir cupom?') }}
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ t('coupons.delete_confirm', 'Tem certeza que deseja excluir o cupom') }}
                    <strong class="text-zinc-900 dark:text-white">"{{ couponToDelete?.code }}"</strong>?
                    {{ t('common.irreversible_action', 'Esta ação não pode ser desfeita.') }}
                </p>
                <div class="mt-5 flex gap-3 justify-end">
                    <Button variant="outline" @click="closeDeleteModal">
                        {{ t('common.cancel', 'Cancelar') }}
                    </Button>
                    <Button variant="destructive" @click="confirmDestroy">
                        {{ t('common.delete', 'Excluir') }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <CupomSidebar
        :open="sidebarOpen"
        :produtos="produtos"
        :coupon="couponToEdit"
        @close="closeSidebar"
        @success="closeSidebar"
    />
</template>
