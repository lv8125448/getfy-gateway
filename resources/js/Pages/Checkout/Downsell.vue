<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import ConversionPixels from '@/components/checkout/ConversionPixels.vue';
import { YOUTUBE_IFRAME_ALLOW, youtubeEmbedUrlFromPageUrl as youtubeEmbedUrl } from '@/lib/youtubeEmbed';

defineOptions({ layout: null });

const props = defineProps({
    token: { type: String, required: true },
    order: { type: Object, default: () => ({}) },
    offer: { type: Object, required: true },
    appearance: { type: Object, default: () => ({}) },
    page: { type: Object, default: () => ({}) },
    config: { type: Object, default: () => ({}) },
    conversion_pixels: { type: Object, default: () => ({}) },
});

const primaryColor = computed(() => props.appearance.primary_color || '#0ea5e9');
const title = computed(() => props.page?.headline || props.appearance.title || 'Última chance com desconto');
const subtitle = computed(() => props.page?.subheadline || props.appearance.subtitle || 'Uma oferta que não pode ficar de fora');
const buttonAccept = computed(() => props.appearance.button_accept || 'Aceitar oferta');
const buttonDecline = computed(() => props.appearance.button_decline || 'Não, obrigado');
const pageBackgroundColor = computed(() => props.page?.background_color || '#f3f4f6');

const loadingAccept = ref(false);
const loadingDecline = ref(false);
const errorMessage = ref('');

function goTo(url) {
    if (url && (url.startsWith('http') || url.startsWith('//'))) {
        window.location.href = url;
    } else if (url) {
        router.visit(url);
    }
}

async function acceptOffer() {
    if (loadingAccept.value) return;
    loadingAccept.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post('/checkout/downsell/accept', { token: props.token }, {
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
        loadingAccept.value = false;
    } catch (e) {
        errorMessage.value = e.response?.data?.message || 'Erro ao processar. Tente novamente.';
        loadingAccept.value = false;
    }
}

async function decline() {
    if (loadingDecline.value) return;
    loadingDecline.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.post('/checkout/downsell/decline', { token: props.token }, {
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
                <div class="p-6 sm:p-8">
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

                    <div class="flex flex-row flex-wrap gap-3 items-start rounded-xl border-2 border-gray-200 bg-gray-50/50 p-4">
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
                        </div>
                    </div>
                    <div v-if="offer.video_url && youtubeEmbedUrl(offer.video_url)" class="mt-3 aspect-video w-full overflow-hidden rounded-lg bg-black">
                        <iframe
                            :src="youtubeEmbedUrl(offer.video_url)"
                            title="Vídeo da oferta"
                            class="h-full w-full"
                            :allow="YOUTUBE_IFRAME_ALLOW"
                            allowfullscreen
                        />
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:gap-4">
                        <button
                            type="button"
                            class="flex-1 rounded-xl px-5 py-3 font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-70"
                            :style="{ backgroundColor: primaryColor }"
                            :disabled="loadingAccept || loadingDecline"
                            @click="acceptOffer"
                        >
                            {{ loadingAccept ? 'Processando...' : buttonAccept }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl border-2 border-gray-300 bg-white py-3 font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-70"
                            :disabled="loadingAccept || loadingDecline"
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
