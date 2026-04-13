<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import ProdutosTabs from '@/components/produtos/ProdutosTabs.vue';
import { useI18n } from '@/composables/useI18n';
import { Handshake, Package, ExternalLink } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const props = defineProps({
    coproduction_pending: { type: Array, default: () => [] },
    coproduction_active: { type: Array, default: () => [] },
});

const pending = computed(() => props.coproduction_pending ?? []);
const active = computed(() => props.coproduction_active ?? []);

function durationPresetLabel(p) {
    if (p === 'eternal') return 'Por tempo indeterminado';
    if (p === '30') return '30 dias';
    if (p === '60') return '60 dias';
    if (p === '90') return '90 dias';
    if (p === '120') return '120 dias';
    return p || '—';
}

function acceptInvite(token) {
    router.post(`/coproducao/convite/${token}/aceitar`, {}, { preserveScroll: true });
}

function openInvitePage(token) {
    window.location.href = `/coproducao/convite/${token}`;
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ t('products.coproduction_page_title', 'Co-produção') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{
                    t(
                        'products.coproduction_page_subtitle',
                        'Produtos em que você é co-produtor e convites aguardando sua aprovação.'
                    )
                }}
            </p>
        </div>

        <ProdutosTabs />

        <!-- Pendentes -->
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ t('products.coproduction_section_pending', 'Pendentes de aprovação') }}
            </h2>
            <div v-if="pending.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="row in pending"
                    :key="row.id"
                    class="flex flex-col rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <div class="flex gap-3">
                        <div class="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                            <img
                                v-if="row.product?.image_url"
                                :src="row.product.image_url"
                                :alt="row.product.name"
                                class="absolute inset-0 h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-700/50"
                            >
                                <Package class="h-7 w-7" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-zinc-900 line-clamp-2 dark:text-white">{{ row.product?.name }}</p>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ t('products.coproduction_by', 'Produtor') }}: {{ row.product?.owner_name || '—' }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ row.commission_percent }}% · {{ durationPresetLabel(row.duration_preset) }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button type="button" class="flex-1" @click="acceptInvite(row.token)">
                            {{ t('products.coproduction_accept', 'Aceitar') }}
                        </Button>
                        <Button type="button" variant="outline" class="flex-1" @click="openInvitePage(row.token)">
                            {{ t('products.coproduction_details', 'Detalhes') }}
                        </Button>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50/80 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400"
            >
                {{ t('products.coproduction_empty_pending', 'Nenhum convite pendente.') }}
            </div>
        </section>

        <!-- Ativas -->
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ t('products.coproduction_section_active', 'Co-produções ativas') }}
            </h2>
            <div v-if="active.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="row in active"
                    :key="row.id"
                    class="flex flex-col rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                >
                    <div class="flex gap-3">
                        <div class="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                            <img
                                v-if="row.product?.image_url"
                                :src="row.product.image_url"
                                :alt="row.product.name"
                                class="absolute inset-0 h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-zinc-100 text-zinc-400 dark:bg-zinc-700/50"
                            >
                                <Handshake class="h-7 w-7" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-zinc-900 line-clamp-2 dark:text-white">{{ row.product?.name }}</p>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ t('products.coproduction_by', 'Produtor') }}: {{ row.product?.owner_name || '—' }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ row.commission_percent }}%
                                <span v-if="row.ends_at" class="text-zinc-500">
                                    · {{ t('products.coproduction_until', 'até') }} {{ new Date(row.ends_at).toLocaleDateString('pt-BR') }}
                                </span>
                                <span v-else class="text-zinc-500"> · {{ durationPresetLabel('eternal') }}</span>
                            </p>
                        </div>
                    </div>
                    <a
                        v-if="row.product?.checkout_slug"
                        :href="`/c/${row.product.checkout_slug}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-primary)] hover:underline"
                    >
                        <ExternalLink class="h-3.5 w-3.5" />
                        {{ t('products.coproduction_view_checkout', 'Ver checkout') }}
                    </a>
                </div>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50/80 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400"
            >
                {{ t('products.coproduction_empty_active', 'Nenhuma co-produção ativa ainda.') }}
            </div>
        </section>
    </div>
</template>
