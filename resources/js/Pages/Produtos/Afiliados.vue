<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import ProdutosTabs from '@/components/produtos/ProdutosTabs.vue';
import { useI18n } from '@/composables/useI18n';
import { Package, ExternalLink, UserPlus } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const props = defineProps({
    affiliate_products: { type: Array, default: () => [] },
});

const list = computed(() => props.affiliate_products ?? []);

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);
}

function isApproved(row) {
    return row.status === 'approved';
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ t('products.affiliates_page_title', 'Afiliados') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('products.affiliates_page_subtitle', 'Produtos em que você é afiliado aprovado.') }}
            </p>
        </div>

        <ProdutosTabs />

        <div v-if="!list.length" class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-12 text-center dark:border-zinc-600 dark:bg-zinc-800/40">
            <UserPlus class="mx-auto h-10 w-10 text-zinc-400" aria-hidden="true" />
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                {{ t('products.affiliates_empty', 'Nenhum produto como afiliado ainda.') }}
            </p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="row in list"
                :key="row.enrollment_id"
                class="group relative flex flex-row gap-3 rounded-xl border border-zinc-200 bg-white p-3 pr-2 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="flex w-20 shrink-0 items-center justify-center self-stretch">
                    <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                        <img
                            v-if="row.image_url"
                            :src="row.image_url"
                            :alt="row.name"
                            class="absolute inset-0 h-full w-full object-cover object-center"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-700/50 dark:text-zinc-500"
                        >
                            <Package class="h-8 w-8" aria-hidden="true" />
                        </div>
                    </div>
                </div>
                <div class="flex min-w-0 flex-1 flex-col px-2">
                    <div class="min-w-0 flex-1">
                        <span class="font-medium text-zinc-900 dark:text-white line-clamp-2 block text-sm leading-tight">
                            {{ row.name }}
                        </span>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-block rounded bg-violet-100 px-1.5 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-900/40 dark:text-violet-300"
                            >
                                {{ t('products.affiliate_badge', 'Afiliado') }}
                            </span>
                            <span
                                :class="[
                                    'inline-block rounded px-1.5 py-0.5 text-xs font-medium',
                                    isApproved(row)
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                                ]"
                            >
                                {{
                                    isApproved(row)
                                        ? t('products.affiliate_status_approved', 'Aprovado')
                                        : t('products.affiliate_status_pending', 'Pendente')
                                }}
                            </span>
                            <span class="inline-block rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                                {{ row.type_label }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ formatBRL(row.price_brl) }}
                        </p>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Link
                            v-if="isApproved(row)"
                            :href="`/produtos/${row.product_id}/painel-afiliado`"
                            class="inline-flex items-center justify-center rounded-lg bg-[var(--color-primary)] px-3 py-1.5 text-xs font-medium text-white transition hover:opacity-90"
                        >
                            {{ t('products.affiliate_panel', 'Painel do afiliado') }}
                        </Link>
                        <a
                            v-if="isApproved(row) && row.affiliate_link"
                            :href="row.affiliate_link"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        >
                            <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ t('products.affiliate_open_checkout', 'Abrir checkout') }}
                        </a>
                        <p v-else-if="isApproved(row) && !row.affiliate_link" class="text-xs text-amber-700 dark:text-amber-200">
                            {{ t('products.affiliate_no_link', 'Link indisponível.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
