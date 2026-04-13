<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import ProdutosTabs from '@/components/produtos/ProdutosTabs.vue';
import ProdutoCreateSidebar from '@/components/produtos/ProdutoCreateSidebar.vue';
import { useI18n } from '@/composables/useI18n';
import {
    MoreVertical,
    Pencil,
    Copy,
    Trash2,
    Package,
    ExternalLink,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const props = defineProps({
    produtos: { type: [Array, Object], default: () => [] },
    productTypes: { type: Array, default: () => [] },
    billingTypes: { type: Array, default: () => [] },
    exchange_rates: { type: Object, default: () => ({ brl_eur: 0.16, brl_usd: 0.18 }) },
    plugin_card_actions: { type: Object, default: () => ({}) },
    plugin_form_sections: { type: Array, default: () => [] },
});

const produtosList = computed(() => props.produtos?.data ?? (Array.isArray(props.produtos) ? props.produtos : []));

const sidebarOpen = ref(false);
const openMenuId = ref(null);
const productToDelete = ref(null);

function openSidebar() {
    sidebarOpen.value = true;
}

function closeSidebar() {
    sidebarOpen.value = false;
}

function toggleMenu(id) {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
    openMenuId.value = null;
}

function handleClickOutside(event) {
    if (openMenuId.value == null) return;
    const menuEl = document.querySelector(`[data-product-menu="${openMenuId.value}"]`);
    if (menuEl && !menuEl.contains(event.target)) {
        closeMenu();
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);
}

function duplicate(p) {
    router.post(`/produtos/${p.id}/duplicate`, {}, { preserveScroll: true });
    closeMenu();
}

function openDeleteModal(p) {
    closeMenu();
    productToDelete.value = p;
}

function closeDeleteModal() {
    productToDelete.value = null;
}

function confirmDestroy() {
    const p = productToDelete.value;
    if (!p) return;
    router.delete(`/produtos/${p.id}`, { preserveScroll: true });
    closeDeleteModal();
}

function pluginActions(productId) {
    return props.plugin_card_actions?.[productId] ?? [];
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ t('sidebar.products', 'Produtos') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('products.subtitle', 'Gerencie seus produtos, ofertas e acessos de checkout.') }}
            </p>
        </div>

        <ProdutosTabs />
        <div class="flex justify-end">
            <Button @click="openSidebar">
                Novo produto
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="p in produtosList"
                :key="p.id"
                class="group relative flex flex-row gap-3 rounded-xl border border-zinc-200 bg-white p-3 pr-2 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800"
            >
                <!-- Coluna da imagem: clicável → edição -->
                <Link
                    :href="`/produtos/${p.id}/edit`"
                    class="flex w-20 shrink-0 items-center justify-center self-stretch"
                >
                    <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                        <img
                            v-if="p.image_url"
                            :src="p.image_url"
                            :alt="p.name"
                            class="absolute inset-0 h-full w-full object-cover object-center"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-700/50 dark:text-zinc-500"
                        >
                            <Package class="h-8 w-8" aria-hidden="true" />
                        </div>
                    </div>
                </Link>
                <!-- Conteúdo à direita (só padding horizontal para não somar com o do card no topo/baixo) -->
                <div class="flex min-w-0 flex-1 flex-col px-2">
                    <div class="flex items-start justify-between gap-1.5">
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="`/produtos/${p.id}/edit`"
                                class="font-medium text-zinc-900 dark:text-white line-clamp-2 block text-sm leading-tight hover:underline"
                            >
                                {{ p.name }}
                            </Link>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                <span class="inline-block rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                    {{ p.type_label }}
                                </span>
                                <span class="inline-block rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                    {{ p.billing_type_label ?? 'Pagamento único' }}
                                </span>
                                <span
                                    :class="[
                                        'inline-block rounded px-2 py-0.5 text-xs font-medium',
                                        p.is_active
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                            : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400',
                                    ]"
                                >
                                    {{ p.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                        </div>
                        <div class="relative shrink-0" :data-product-menu="p.id">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                                aria-label="Abrir menu"
                                aria-expanded="openMenuId === p.id"
                                @click="toggleMenu(p.id)"
                            >
                                <MoreVertical class="h-3.5 w-3.5" />
                            </button>
                            <div
                                v-show="openMenuId === p.id"
                                class="absolute right-0 top-full z-50 mt-1 w-48 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <Link
                                    :href="`/produtos/${p.id}/edit`"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    @click="closeMenu"
                                >
                                    <Pencil class="h-4 w-4 shrink-0" />
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    @click="duplicate(p)"
                                >
                                    <Copy class="h-4 w-4 shrink-0" />
                                    Duplicar
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                    @click="openDeleteModal(p)"
                                >
                                    <Trash2 class="h-4 w-4 shrink-0" />
                                    Excluir
                                </button>
                                <template v-for="(action, actIdx) in pluginActions(p.id)" :key="`plugin-${p.id}-${actIdx}`">
                                    <a
                                        v-if="action.href"
                                        :href="action.href"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                        @click="closeMenu"
                                    >
                                        <ExternalLink v-if="!action.icon" class="h-4 w-4 shrink-0" />
                                        <component v-else :is="action.icon" class="h-4 w-4 shrink-0" />
                                        {{ action.label }}
                                    </a>
                                    <span v-else class="block border-t border-zinc-100 px-3 py-1 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                        {{ action.label }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                    <p class="mt-0.5 text-sm font-semibold text-[var(--color-primary)]">
                        {{ formatBRL(p.price_brl ?? p.price) }}
                    </p>
                    <a
                        v-if="p.checkout_slug"
                        :href="`/c/${p.checkout_slug}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-0.5 text-xs text-zinc-500 hover:text-[var(--color-primary)] dark:text-zinc-400 dark:hover:text-[var(--color-primary)]"
                    >
                        Ver checkout →
                    </a>
                </div>
            </div>
        </div>

        <nav
            v-if="produtos?.links?.length > 3"
            class="flex items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in produtos.links"
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

        <div
            v-if="!produtosList.length"
            class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700"
        >
            <Package class="h-14 w-14 text-zinc-400 dark:text-zinc-500" />
            <p class="mt-3 text-zinc-600 dark:text-zinc-400">Nenhum produto ainda.</p>
            <Button class="mt-4" @click="openSidebar">
                Criar primeiro produto
            </Button>
        </div>
    </div>

    <!-- Modal de confirmação de exclusão -->
    <Teleport to="body">
        <div
            v-if="productToDelete"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-modal-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="closeDeleteModal"
            />
            <div
                class="relative w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <h2 id="delete-modal-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                    Excluir produto?
                </h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Tem certeza que deseja excluir
                    <strong class="text-zinc-900 dark:text-white">"{{ productToDelete?.name }}"</strong>?
                    Esta ação não pode ser desfeita.
                </p>
                <div class="mt-5 flex gap-3 justify-end">
                    <Button variant="outline" @click="closeDeleteModal">
                        Cancelar
                    </Button>
                    <Button variant="destructive" @click="confirmDestroy">
                        Excluir
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <ProdutoCreateSidebar
        :open="sidebarOpen"
        :product-types="productTypes"
        :billing-types="billingTypes"
        :exchange-rates="exchange_rates"
        :plugin-form-sections="plugin_form_sections"
        @close="closeSidebar"
        @success="closeSidebar"
    />
</template>
