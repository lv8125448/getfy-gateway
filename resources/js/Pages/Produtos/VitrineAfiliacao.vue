<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/composables/useI18n';
import { Search, X, LayoutGrid, Flame } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const { t } = useI18n();

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    product_types: { type: Array, default: () => [] },
});

const list = computed(() => props.products?.data ?? []);
const selected = ref(null);

const filterForm = useForm({
    q: props.filters?.q ?? '',
    type: props.filters?.type ?? '',
    sort: props.filters?.sort ?? 'hot',
});

function applyFilters() {
    filterForm.get('/produtos/vitrine-afiliacao', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let debounceId;
watch(
    () => filterForm.q,
    () => {
        clearTimeout(debounceId);
        debounceId = setTimeout(applyFilters, 350);
    }
);

function onSortChange() {
    applyFilters();
}

function onTypeChange() {
    applyFilters();
}

function formatMoney(v, cur) {
    const n = Number(v);
    if (cur === 'USD') return `US$ ${n.toFixed(2)}`;
    if (cur === 'EUR') return `€ ${n.toFixed(2)}`;
    return `R$ ${n.toFixed(2).replace('.', ',')}`;
}

function openDetail(p) {
    selected.value = p;
}

function closeDetail() {
    selected.value = null;
}

function solicit(productId) {
    router.post(
        `/produtos/vitrine-afiliacao/${productId}/solicitar`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['products'] });
            },
        }
    );
}

function fallbackCopy(text) {
    try {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.top = '0';
        el.style.left = '0';
        el.style.width = '2em';
        el.style.height = '2em';
        el.style.padding = '0';
        el.style.border = 'none';
        el.style.outline = 'none';
        el.style.boxShadow = 'none';
        el.style.background = 'transparent';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.focus();
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    } catch (_) {
        return false;
    }
}

function copyToClipboard(text) {
    const s = text != null ? String(text) : '';
    if (!s) return Promise.resolve(false);
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        return navigator.clipboard.writeText(s).then(() => true).catch(() => fallbackCopy(s));
    }
    return Promise.resolve(fallbackCopy(s));
}

function copyLink(url) {
    copyToClipboard(url);
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ t('products.showcase.title', 'Vitrine') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('products.showcase.subtitle', 'Produtos abertos a afiliados. Ordene por mais vendidos (quentes) ou explore por categoria.') }}
            </p>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/80 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="relative min-w-[200px] flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                <input
                    v-model="filterForm.q"
                    type="search"
                    :placeholder="t('products.showcase.search', 'Pesquisar')"
                    class="w-full rounded-lg border border-zinc-200 bg-zinc-50 py-2 pl-9 pr-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                />
            </div>
            <select
                v-model="filterForm.type"
                class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                @change="onTypeChange"
            >
                <option value="">{{ t('products.showcase.category_all', 'Todas as categorias') }}</option>
                <option v-for="opt in product_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select
                v-model="filterForm.sort"
                class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                @change="onSortChange"
            >
                <option value="hot">{{ t('products.showcase.sort_hot', 'Mais quentes') }}</option>
                <option value="name">{{ t('products.showcase.sort_name', 'Nome') }}</option>
                <option value="price_asc">{{ t('products.showcase.sort_price_asc', 'Menor preço') }}</option>
                <option value="price_desc">{{ t('products.showcase.sort_price_desc', 'Maior preço') }}</option>
            </select>
        </div>

        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                <Flame class="h-4 w-4 text-emerald-500" />
                {{ t('products.showcase.section_hot', 'Mais quentes') }}
            </h2>
            <div v-if="list.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="p in list"
                    :key="p.id"
                    type="button"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50/80 text-left shadow-sm transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/80 dark:hover:border-zinc-600"
                    @click="openDetail(p)"
                >
                    <div class="relative aspect-square w-full overflow-hidden bg-zinc-200 dark:bg-zinc-700">
                        <img
                            v-if="p.image_url"
                            :src="p.image_url"
                            :alt="p.name"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-zinc-400">
                            <LayoutGrid class="h-12 w-12 opacity-50" />
                        </div>
                        <span
                            v-if="p.is_own_product"
                            class="absolute left-2 top-2 inline-flex rounded-full bg-zinc-900/85 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white dark:bg-white/90 dark:text-zinc-900"
                        >
                            {{ t('products.showcase.your_product_badge', 'Seu produto') }}
                        </span>
                        <span
                            class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-emerald-600/90 px-2 py-0.5 text-xs font-semibold text-white"
                            :title="t('products.showcase.sales_badge', 'Vendas concluídas (quentes)')"
                        >
                            {{ p.sales_count }}°
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <span class="line-clamp-2 font-semibold text-zinc-900 dark:text-white">{{ p.name }}</span>
                            <span class="shrink-0 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                {{ p.affiliate_commission_percent }}%
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ p.producer_name }}</p>
                        <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ formatMoney(p.price, p.currency) }}</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">
                            {{ t('products.showcase.you_receive', 'Você recebe até') }} R$ {{ p.commission_max_formatted }}
                        </p>
                    </div>
                </button>
            </div>
            <p v-else class="rounded-xl border border-dashed border-zinc-200 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                {{ t('products.showcase.empty', 'Nenhum produto na vitrine com estes filtros.') }}
            </p>
        </section>

        <div v-if="products.links && products.links.length > 3" class="flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, i) in products.links"
                :key="i"
                :href="link.url || undefined"
                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-sm dark:border-zinc-600"
                :class="link.active ? 'bg-[var(--color-primary)] text-white border-transparent' : 'text-zinc-700 dark:text-zinc-300'"
                v-html="link.label"
            />
        </div>

        <!-- Sidebar detalhe -->
        <Teleport to="body">
            <div
                v-if="selected"
                class="fixed inset-0 z-[100050] flex justify-end bg-black/40"
                role="dialog"
                aria-modal="true"
                @click.self="closeDetail"
            >
                <div class="flex h-full w-full max-w-md flex-col bg-white shadow-xl dark:bg-zinc-900" @click.stop>
                    <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ selected.name }}</h3>
                        <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closeDetail">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4">
                        <div class="relative mb-4 aspect-video w-full overflow-hidden rounded-xl bg-zinc-200 dark:bg-zinc-700">
                            <img v-if="selected.image_url" :src="selected.image_url" :alt="selected.name" class="h-full w-full object-cover" />
                        </div>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ formatMoney(selected.price, selected.currency) }}</p>
                        <p class="mt-1 text-sm text-emerald-600 dark:text-emerald-400">
                            {{ t('products.showcase.you_receive', 'Você recebe até') }} R$ {{ selected.commission_max_formatted }} ({{
                                selected.affiliate_commission_percent
                            }}%)
                        </p>
                        <p v-if="selected.affiliate_showcase_description" class="mt-4 text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-wrap">
                            {{ selected.affiliate_showcase_description }}
                        </p>
                        <a
                            v-if="selected.affiliate_page_url"
                            :href="selected.affiliate_page_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex text-sm font-medium text-[var(--color-primary)] hover:underline"
                        >
                            {{ t('products.showcase.affiliate_page', 'Página de afiliados') }}
                        </a>
                        <p v-if="selected.affiliate_support_email" class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ t('products.showcase.support', 'Suporte') }}: {{ selected.affiliate_support_email }}
                        </p>
                        <div v-if="selected.order_bumps_count > 0" class="mt-3 text-xs text-zinc-500">
                            {{ t('products.showcase.order_bump', 'Inclui order bump') }}
                        </div>

                        <div v-if="selected.enrollment?.status === 'approved' && selected.enrollment.affiliate_link" class="mt-6 space-y-2">
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ t('products.showcase.your_link', 'Seu link de afiliação') }}</p>
                            <div class="flex flex-wrap gap-2">
                                <code class="max-w-full flex-1 truncate rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800">{{ selected.enrollment.affiliate_link }}</code>
                                <Button type="button" size="sm" variant="outline" @click="copyLink(selected.enrollment.affiliate_link)">
                                    {{ t('common.copy', 'Copiar') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                        <template v-if="selected.is_own_product">
                            <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                                {{ t('products.showcase.own_product_hint', 'Este é o seu produto. Use a edição para gerenciar afiliados.') }}
                            </p>
                            <Link
                                :href="`/produtos/${selected.id}/edit?tab=afiliados`"
                                class="mt-3 flex w-full items-center justify-center rounded-lg bg-zinc-100 px-4 py-2.5 text-sm font-medium text-zinc-900 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                            >
                                {{ t('products.showcase.edit_affiliate_settings', 'Abrir configurações de afiliados') }}
                            </Link>
                        </template>
                        <template v-else>
                            <Button
                                v-if="!selected.enrollment || selected.enrollment.status === 'rejected' || selected.enrollment.status === 'revoked'"
                                type="button"
                                class="w-full"
                                @click="solicit(selected.id)"
                            >
                                {{ t('products.showcase.request', 'Solicitar afiliação') }}
                            </Button>
                            <p v-else-if="selected.enrollment.status === 'pending'" class="text-center text-sm text-amber-700 dark:text-amber-300">
                                {{ t('products.showcase.pending', 'Aguardando aprovação do produtor.') }}
                            </p>
                            <Button v-else-if="selected.enrollment.status === 'approved'" type="button" variant="outline" class="w-full" @click="closeDetail">
                                {{ t('common.close', 'Fechar') }}
                            </Button>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
