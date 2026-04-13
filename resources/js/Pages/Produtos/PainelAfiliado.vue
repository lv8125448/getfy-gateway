<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import ProdutosTabs from '@/components/produtos/ProdutosTabs.vue';
import { useI18n } from '@/composables/useI18n';
import {
    mergeConversionPixels,
    newMetaEntry,
    newTiktokEntry,
    newGoogleAdsEntry,
    newGaEntry,
    randomClientId,
} from '@/lib/conversionPixelsForm';
import { ArrowLeft, Copy, Check, ExternalLink, Plus, Trash2 } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();
const page = usePage();

const props = defineProps({
    produto: { type: Object, required: true },
    enrollment: { type: Object, required: true },
});

const selectedPixelTab = ref('meta');
const copied = ref(false);
let copyTimer;

const PIXEL_TABS = computed(() => [
    { id: 'meta', label: 'Meta Ads', image: '/images/pixels/meta.png' },
    { id: 'tiktok', label: 'TikTok Ads', image: '/images/pixels/tiktok.png' },
    { id: 'google_ads', label: 'Google Ads', image: '/images/pixels/googleads.png' },
    { id: 'google_analytics', label: 'Google Analytics', image: '/images/pixels/google-analytics.png' },
    { id: 'custom_script', label: t('products.edit.custom_script', 'Script personalizado'), image: '/images/pixels/script.png' },
]);

const form = useForm({
    conversion_pixels: mergeConversionPixels(props.enrollment.conversion_pixels),
});

const inputClass =
    'block w-full rounded-xl border-2 border-zinc-200 bg-white px-4 py-2.5 text-zinc-900 placeholder-zinc-400 transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500';

function submit() {
    form.put(`/produtos/${props.produto.id}/painel-afiliado`, { preserveScroll: true });
}

function fallbackCopy(text) {
    try {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.top = '0';
        el.style.left = '0';
        el.style.opacity = '0';
        document.body.appendChild(el);
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

function copyAffiliateLink() {
    const url = props.enrollment.affiliate_link;
    if (!url) return;
    copyToClipboard(url).then((ok) => {
        if (ok) {
            copied.value = true;
            clearTimeout(copyTimer);
            copyTimer = setTimeout(() => {
                copied.value = false;
            }, 2000);
        }
    });
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <Link
                href="/produtos/afiliados"
                class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                {{ t('products.tab_affiliates', 'Afiliados') }}
            </Link>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ t('products.affiliate_panel_title', 'Painel do afiliado') }}
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ produto.name }} — {{ t('products.affiliate_panel_subtitle', 'Seu link e pixels.') }}
            </p>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </div>

        <ProdutosTabs />

        <section class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-800/95">
            <div class="border-b border-zinc-200/80 bg-gradient-to-r from-zinc-50/90 to-zinc-100/50 px-6 py-4 dark:from-zinc-800/80 dark:to-zinc-800/50">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">
                    {{ t('products.affiliate_link_section', 'Link de afiliação') }}
                </h2>
            </div>
            <div class="space-y-3 p-6">
                <div
                    v-if="enrollment.affiliate_link"
                    class="flex flex-col gap-2 sm:flex-row sm:items-center"
                >
                    <code
                        class="min-w-0 flex-1 truncate rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-800 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200"
                    >
                        {{ enrollment.affiliate_link }}
                    </code>
                    <Button type="button" variant="outline" class="shrink-0 gap-2" @click="copyAffiliateLink">
                        <Check v-if="copied" class="h-4 w-4 text-emerald-600" aria-hidden="true" />
                        <Copy v-else class="h-4 w-4" aria-hidden="true" />
                        {{ copied ? t('common.copied', 'Copiado') : t('products.affiliate_copy_link', 'Copiar link') }}
                    </Button>
                </div>
                <p v-else class="text-sm text-amber-700 dark:text-amber-200">
                    {{ t('products.affiliate_no_link', 'Link indisponível.') }}
                </p>
                <a
                    v-if="enrollment.affiliate_link"
                    :href="enrollment.affiliate_link"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary)] hover:underline"
                >
                    <ExternalLink class="h-4 w-4" aria-hidden="true" />
                    {{ t('products.affiliate_open_checkout', 'Abrir checkout') }}
                </a>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-800/95">
                <div class="border-b border-zinc-200/80 bg-gradient-to-r from-zinc-50/90 to-zinc-100/50 px-6 py-5 dark:from-zinc-800/80 dark:to-zinc-800/50">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">
                        {{ t('products.edit.conversion_pixels', 'Pixels de conversão') }}
                    </h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ t('products.affiliate_pixels_only_hint', 'Somente seus pixels quando a venda vier pelo seu ref.') }}
                    </p>
                </div>
                <div class="space-y-6 p-6">
                    <div class="flex gap-3 overflow-x-auto pb-2 scroll-smooth" style="scrollbar-width: thin;">
                        <button
                            v-for="tab in PIXEL_TABS"
                            :key="tab.id"
                            type="button"
                            :class="[
                                'flex shrink-0 flex-col items-center justify-center gap-1.5 rounded-xl border-2 p-4 w-28 h-24 transition-all duration-200',
                                selectedPixelTab === tab.id
                                    ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 dark:bg-[var(--color-primary)]/20'
                                    : 'border-zinc-200 bg-zinc-50 hover:border-zinc-300 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:border-zinc-500 dark:hover:bg-zinc-700',
                            ]"
                            @click="selectedPixelTab = tab.id"
                        >
                            <img :src="tab.image" :alt="tab.label" class="h-8 w-8 object-contain" @error="($e) => $e.target && ($e.target.style.display = 'none')" />
                            <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ tab.label }}</span>
                        </button>
                    </div>

                    <div v-if="selectedPixelTab === 'meta'" class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Meta Ads (Facebook)</h3>
                            <div class="flex items-center gap-3">
                                <Button type="button" variant="outline" size="sm" :disabled="!form.conversion_pixels.meta.enabled" @click="form.conversion_pixels.meta.entries.push(newMetaEntry())">
                                    <Plus class="h-4 w-4 mr-1" /> {{ t('products.edit.add_pixel', 'Adicionar pixel') }}
                                </Button>
                                <Toggle v-model="form.conversion_pixels.meta.enabled" />
                            </div>
                        </div>
                        <template v-if="form.conversion_pixels.meta.enabled">
                            <div v-for="(item, idx) in form.conversion_pixels.meta.entries" :key="item.id" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Pixel {{ idx + 1 }}</span>
                                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" @click="form.conversion_pixels.meta.entries.splice(idx, 1)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Pixel ID</label>
                                    <input v-model="item.pixel_id" type="text" placeholder="Ex: 123456789" :class="inputClass" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Access Token (CAPI)</label>
                                    <input v-model="item.access_token" type="password" placeholder="Token para Conversions API" :class="inputClass" autocomplete="off" />
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Usado para enviar eventos server-side (CAPI).</p>
                                </div>
                                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                    <Checkbox v-model="item.fire_purchase_on_pix" label="Disparar evento Purchase ao gerar PIX?" />
                                    <Checkbox v-model="item.fire_purchase_on_boleto" label="Disparar evento Purchase ao gerar Boleto?" />
                                    <Checkbox v-model="item.disable_order_bump_events" label="Desativar eventos de order bumps?" />
                                </div>
                            </div>
                            <p v-if="form.conversion_pixels.meta.entries.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('products.edit.no_pixels', 'Nenhum pixel.') }}</p>
                        </template>
                    </div>

                    <div v-if="selectedPixelTab === 'tiktok'" class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">TikTok Ads</h3>
                            <div class="flex items-center gap-3">
                                <Button type="button" variant="outline" size="sm" :disabled="!form.conversion_pixels.tiktok.enabled" @click="form.conversion_pixels.tiktok.entries.push(newTiktokEntry())">
                                    <Plus class="h-4 w-4 mr-1" /> {{ t('products.edit.add_pixel', 'Adicionar pixel') }}
                                </Button>
                                <Toggle v-model="form.conversion_pixels.tiktok.enabled" />
                            </div>
                        </div>
                        <template v-if="form.conversion_pixels.tiktok.enabled">
                            <div v-for="(item, idx) in form.conversion_pixels.tiktok.entries" :key="item.id" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Pixel {{ idx + 1 }}</span>
                                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" @click="form.conversion_pixels.tiktok.entries.splice(idx, 1)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Pixel ID</label>
                                    <input v-model="item.pixel_id" type="text" placeholder="Ex: C1X2Y3Z4..." :class="inputClass" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Access Token</label>
                                    <input v-model="item.access_token" type="password" placeholder="Token do TikTok Events API" :class="inputClass" autocomplete="off" />
                                </div>
                                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                    <Checkbox v-model="item.fire_purchase_on_pix" label="Disparar evento Purchase ao gerar PIX?" />
                                    <Checkbox v-model="item.fire_purchase_on_boleto" label="Disparar evento Purchase ao gerar Boleto?" />
                                    <Checkbox v-model="item.disable_order_bump_events" label="Desativar eventos de order bumps?" />
                                </div>
                            </div>
                            <p v-if="form.conversion_pixels.tiktok.entries.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('products.edit.no_pixels', 'Nenhum pixel.') }}</p>
                        </template>
                    </div>

                    <div v-if="selectedPixelTab === 'google_ads'" class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Google Ads</h3>
                            <div class="flex items-center gap-3">
                                <Button type="button" variant="outline" size="sm" :disabled="!form.conversion_pixels.google_ads.enabled" @click="form.conversion_pixels.google_ads.entries.push(newGoogleAdsEntry())">
                                    <Plus class="h-4 w-4 mr-1" /> {{ t('products.edit.add_conversion', 'Adicionar conversão') }}
                                </Button>
                                <Toggle v-model="form.conversion_pixels.google_ads.enabled" />
                            </div>
                        </div>
                        <template v-if="form.conversion_pixels.google_ads.enabled">
                            <div v-for="(item, idx) in form.conversion_pixels.google_ads.entries" :key="item.id" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Conversão {{ idx + 1 }}</span>
                                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" @click="form.conversion_pixels.google_ads.entries.splice(idx, 1)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Conversion ID</label>
                                    <input v-model="item.conversion_id" type="text" placeholder="Ex: AW-123456789" :class="inputClass" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Conversion Label</label>
                                    <input v-model="item.conversion_label" type="text" placeholder="Ex: AbCdEfGhIjKlMn" :class="inputClass" />
                                </div>
                                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                    <Checkbox v-model="item.fire_purchase_on_pix" label="Disparar evento Purchase ao gerar PIX?" />
                                    <Checkbox v-model="item.fire_purchase_on_boleto" label="Disparar evento Purchase ao gerar Boleto?" />
                                    <Checkbox v-model="item.disable_order_bump_events" label="Desativar eventos de order bumps?" />
                                </div>
                            </div>
                            <p v-if="form.conversion_pixels.google_ads.entries.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('products.edit.no_conversion', 'Nenhuma conversão.') }}</p>
                        </template>
                    </div>

                    <div v-if="selectedPixelTab === 'google_analytics'" class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Google Analytics (GA4)</h3>
                            <div class="flex items-center gap-3">
                                <Button type="button" variant="outline" size="sm" :disabled="!form.conversion_pixels.google_analytics.enabled" @click="form.conversion_pixels.google_analytics.entries.push(newGaEntry())">
                                    <Plus class="h-4 w-4 mr-1" /> {{ t('products.edit.add_property', 'Adicionar propriedade') }}
                                </Button>
                                <Toggle v-model="form.conversion_pixels.google_analytics.enabled" />
                            </div>
                        </div>
                        <template v-if="form.conversion_pixels.google_analytics.enabled">
                            <div v-for="(item, idx) in form.conversion_pixels.google_analytics.entries" :key="item.id" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">GA4 {{ idx + 1 }}</span>
                                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" @click="form.conversion_pixels.google_analytics.entries.splice(idx, 1)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Measurement ID</label>
                                    <input v-model="item.measurement_id" type="text" placeholder="Ex: G-XXXXXXXXXX" :class="inputClass" />
                                </div>
                                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                    <Checkbox v-model="item.fire_purchase_on_pix" label="Disparar evento Purchase ao gerar PIX?" />
                                    <Checkbox v-model="item.fire_purchase_on_boleto" label="Disparar evento Purchase ao gerar Boleto?" />
                                    <Checkbox v-model="item.disable_order_bump_events" label="Desativar eventos de order bumps?" />
                                </div>
                            </div>
                            <p v-if="form.conversion_pixels.google_analytics.entries.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('products.edit.no_property', 'Nenhuma propriedade.') }}</p>
                        </template>
                    </div>

                    <div v-if="selectedPixelTab === 'custom_script'" class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ t('products.edit.custom_scripts', 'Scripts personalizados') }}</h3>
                            <Button type="button" variant="outline" size="sm" @click="form.conversion_pixels.custom_script.push({ id: randomClientId(), name: '', script: '' })">
                                <Plus class="h-4 w-4 mr-1" /> {{ t('products.edit.add_pixel', 'Adicionar pixel') }}
                            </Button>
                        </div>
                        <div v-for="(item, idx) in form.conversion_pixels.custom_script" :key="item.id" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800 space-y-3">
                            <div class="flex items-center gap-2">
                                <input v-model="item.name" type="text" placeholder="Nome (opcional)" :class="inputClass + ' flex-1'" />
                                <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" @click="form.conversion_pixels.custom_script.splice(idx, 1)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <textarea v-model="item.script" rows="4" :class="inputClass + ' font-mono text-sm'" placeholder="Cole o código do pixel aqui" />
                        </div>
                        <p v-if="form.conversion_pixels.custom_script.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('products.edit.no_custom_script', 'Nenhum script.') }}</p>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <Button type="submit" :disabled="form.processing">{{ t('products.edit.save_changes', 'Salvar alterações') }}</Button>
                <Link
                    href="/produtos/afiliados"
                    class="inline-flex items-center rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800"
                >
                    {{ t('common.cancel', 'Cancelar') }}
                </Link>
            </div>
        </form>
    </div>
</template>
