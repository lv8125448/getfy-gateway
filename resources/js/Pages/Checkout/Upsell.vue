<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { CheckCircle2 } from 'lucide-vue-next';
import ConversionPixels from '@/components/checkout/ConversionPixels.vue';
import { YOUTUBE_IFRAME_ALLOW, youtubeEmbedUrlFromPageUrl as youtubeEmbedUrl } from '@/lib/youtubeEmbed';

defineOptions({ layout: null });

const props = defineProps({
    token: { type: String, required: true },
    order: { type: Object, default: () => ({}) },
    product_just_bought: { type: Object, default: null },
    offers: { type: Array, default: () => [] },
    appearance: { type: Object, default: () => ({}) },
    page: { type: Object, default: () => ({}) },
    config: { type: Object, default: () => ({}) },
    conversion_pixels: { type: Object, default: () => ({}) },
});

const primaryColor = computed(() => props.appearance.primary_color || '#0ea5e9');
const title = computed(() => props.page?.headline || props.appearance.title || 'Quer levar isso também?');
const subtitle = computed(() => props.page?.subheadline || props.appearance.subtitle || 'Uma oferta exclusiva preparada para você');
const buttonAccept = computed(() => props.appearance.button_accept || 'Sim, quero aproveitar');
const buttonDecline = computed(() => props.appearance.button_decline || 'Não, obrigado');
const showProductJustBought = computed(() => props.page?.show_product_just_bought !== false);
const pageBackgroundColor = computed(() => props.page?.background_color || '#f3f4f6');

const loadingAccept = ref(null);
const loadingDecline = ref(false);
const errorMessage = ref('');
const multipleOffers = computed(() => props.offers.length >= 2);
const selectedIndices = ref([]);

function goTo(url) {
    if (url && (url.startsWith('http') || url.startsWith('//'))) {
        window.location.href = url;
    } else if (url) {
        router.visit(url);
    }
}

function toggleOffer(idx) {
    const i = selectedIndices.value.indexOf(idx);
    if (i === -1) {
        selectedIndices.value = [...selectedIndices.value, idx];
    } else {
        selectedIndices.value = selectedIndices.value.filter((n) => n !== idx);
    }
}

function isSelected(idx) {
    return selectedIndices.value.includes(idx);
}

async function acceptOffer(offer) {
    if (loadingAccept.value !== null) return;
    const offerKey = offer.product_offer_id ?? offer.product_id;
    loadingAccept.value = offerKey;
    errorMessage.value = '';
    try {
        const { data } = await axios.post('/checkout/upsell/accept', {
            token: props.token,
            product_offer_id: offer.product_offer_id || undefined,
            product_id: offer.product_offer_id ? undefined : offer.product_id,
        }, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            validateStatus: () => true,
        });
        if (data.redirect_url) {
            goTo(data.redirect_url);
            return;
        }
        if (data.message) {
            errorMessage.value = data.message;
        }
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Erro ao processar. Tente novamente.';
    } finally {
        loadingAccept.value = null;
    }
}

async function acceptSelected() {
    if (loadingAccept.value !== null || selectedIndices.value.length === 0) return;
    loadingAccept.value = 'multiple';
    errorMessage.value = '';
    try {
        const items = selectedIndices.value.map((idx) => {
            const o = props.offers[idx];
            return {
                product_id: o.product_id,
                product_offer_id: o.product_offer_id || undefined,
            };
        }).filter((i) => i.product_id);
        const { data } = await axios.post('/checkout/upsell/accept', {
            token: props.token,
            items,
        }, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            validateStatus: () => true,
        });
        if (data.redirect_url) {
            goTo(data.redirect_url);
            return;
        }
        if (data.message) {
            errorMessage.value = data.message;
        }
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Erro ao processar. Tente novamente.';
    } finally {
        loadingAccept.value = null;
    }
}

async function decline() {
    if (loadingDecline.value) return;
    loadingDecline.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post('/checkout/upsell/decline', { token: props.token }, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            validateStatus: () => true,
        });
        if (data.redirect_url) {
            goTo(data.redirect_url);
            return;
        }
    } catch {
        loadingDecline.value = false;
    }
    loadingDecline.value = false;
}
</script>

<template>
    <ConversionPixels :pixels="props.conversion_pixels" />
    <Head>
        <title>{{ title }}</title>
    </Head>
    <div class="min-h-screen px-4 py-6 sm:py-8" :style="{ backgroundColor: pageBackgroundColor }">
        <div class="mx-auto max-w-2xl">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg">
                <div v-if="page?.hero_image" class="aspect-video w-full overflow-hidden bg-gray-200">
                    <img :src="page.hero_image" alt="" class="h-full w-full object-cover" />
                </div>
                <div v-if="page?.hero_video_url && youtubeEmbedUrl(page.hero_video_url)" class="aspect-video w-full overflow-hidden bg-black">
                    <iframe
                        :src="youtubeEmbedUrl(page.hero_video_url)"
                        title="Vídeo"
                        class="h-full w-full"
                        :allow="YOUTUBE_IFRAME_ALLOW"
                        allowfullscreen
                    />
                </div>
                <div
                    class="p-6 sm:p-8"
                    :class="{ 'pt-8 sm:pt-10': page?.hero_image || (page?.hero_video_url && youtubeEmbedUrl(page.hero_video_url)) }"
                >
                    <div v-if="showProductJustBought" class="mb-6 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3">
                        <CheckCircle2 class="h-8 w-8 shrink-0 text-emerald-600" />
                        <div>
                            <h1 class="text-lg font-bold text-gray-900">Compra aprovada!</h1>
                            <p v-if="product_just_bought" class="text-sm text-gray-600">
                                Você comprou: <strong>{{ product_just_bought.name }}</strong>
                            </p>
                        </div>
                    </div>

                    <h2 class="mb-1 text-xl font-bold text-gray-900" :style="{ color: primaryColor }">
                        {{ title }}
                    </h2>
                    <p class="mb-4 text-sm text-gray-600">
                        {{ subtitle }}
                    </p>
                    <p v-if="page?.body_text" class="mb-6 whitespace-pre-wrap text-sm text-gray-700">
                        {{ page.body_text }}
                    </p>

                    <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ errorMessage }}
                    </p>

                    <div class="space-y-6">
                        <div
                            v-for="(offer, idx) in offers"
                            :key="idx"
                            class="flex flex-col rounded-xl border-2 p-4"
                            :class="[
                                multipleOffers && isSelected(idx) ? 'border-gray-200 bg-sky-50/50' : 'border-gray-200 bg-gray-50/50',
                                multipleOffers && 'cursor-pointer'
                            ]"
                            :style="multipleOffers && isSelected(idx) ? { borderColor: primaryColor, borderWidth: '2px' } : {}"
                            :role="multipleOffers ? 'button' : undefined"
                            :tabindex="multipleOffers ? 0 : undefined"
                            :aria-pressed="multipleOffers ? isSelected(idx) : undefined"
                            @click="multipleOffers && toggleOffer(idx)"
                            @keydown.enter.space.prevent="multipleOffers && toggleOffer(idx)"
                        >
                            <div class="flex flex-row flex-wrap gap-3 items-start">
                                <div v-if="multipleOffers" class="flex shrink-0 items-start pt-0.5" @click.stop>
                                    <input
                                        type="checkbox"
                                        :id="`offer-${idx}`"
                                        :checked="isSelected(idx)"
                                        class="h-5 w-5 rounded border-gray-300 focus:ring-2 focus:ring-offset-1"
                                        :style="{ accentColor: primaryColor }"
                                        @change="toggleOffer(idx)"
                                    />
                                    <label :for="`offer-${idx}`" class="sr-only">Selecionar {{ offer.name }}</label>
                                </div>
                                <div class="h-20 w-20 shrink-0 sm:h-28 sm:w-28">
                                    <img
                                        :src="offer.image_url || 'https://placehold.co/96x96/e2e8f0/64748b?text=Oferta'"
                                        :alt="offer.name"
                                        class="h-full w-full rounded-lg object-cover"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-gray-900">{{ offer.name }}</h3>
                                    <p v-if="offer.description" class="mt-2 text-sm text-gray-600">
                                        {{ offer.description }}
                                    </p>
                                    <p class="mt-2 text-lg font-bold" :style="{ color: primaryColor }">
                                        {{ offer.price_formatted }}
                                    </p>
                                    <button
                                        v-if="!multipleOffers"
                                        type="button"
                                        class="mt-3 rounded-xl px-5 py-2.5 font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-70"
                                        :style="{ backgroundColor: primaryColor }"
                                        :disabled="loadingAccept !== null"
                                        @click="acceptOffer(offer)"
                                    >
                                        {{ loadingAccept === (offer.product_offer_id ?? offer.product_id) ? 'Processando...' : buttonAccept }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="offer.video_url && youtubeEmbedUrl(offer.video_url)" class="mt-3 w-full aspect-video overflow-hidden rounded-lg bg-black">
                                <iframe
                                    :src="youtubeEmbedUrl(offer.video_url)"
                                    title="Vídeo da oferta"
                                    class="h-full w-full"
                                    :allow="YOUTUBE_IFRAME_ALLOW"
                                    allowfullscreen
                                />
                            </div>
                        </div>
                    </div>

                    <template v-if="multipleOffers">
                        <div class="mt-6 flex flex-col gap-3 border-t border-gray-200 pt-6">
                            <button
                                type="button"
                                class="w-full rounded-xl px-5 py-3.5 font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-70"
                                :style="{ backgroundColor: primaryColor }"
                                :disabled="loadingAccept !== null || selectedIndices.length === 0"
                                @click="acceptSelected"
                            >
                                {{ loadingAccept === 'multiple' ? 'Processando...' : buttonAccept }}
                            </button>
                            <button
                                type="button"
                                class="w-full rounded-xl border-2 border-gray-300 bg-white py-3 font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-70"
                                :disabled="loadingDecline"
                                @click="decline"
                            >
                                {{ loadingDecline ? 'Redirecionando...' : buttonDecline }}
                            </button>
                        </div>
                    </template>
                    <div v-else class="mt-6 border-t border-gray-200 pt-6">
                        <button
                            type="button"
                            class="w-full rounded-xl border-2 border-gray-300 bg-white py-3 font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-70"
                            :disabled="loadingDecline"
                            @click="decline"
                        >
                            {{ loadingDecline ? 'Redirecionando...' : buttonDecline }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
