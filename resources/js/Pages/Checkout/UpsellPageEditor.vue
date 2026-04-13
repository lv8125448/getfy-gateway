<script setup>
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { useSidebar } from '@/composables/useSidebar';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import ImageUpload from '@/components/checkout/ImageUpload.vue';
import { ChevronDown, ChevronRight, Save, Type, Palette, Package } from 'lucide-vue-next';
import { YOUTUBE_IFRAME_ALLOW, youtubeEmbedUrlFromPageUrl as youtubeEmbedUrl } from '@/lib/youtubeEmbed';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    produto: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    products_for_upsell: { type: Array, default: () => [] },
    type: { type: String, default: 'upsell' },
});

const sectionsOpen = ref({
    page: true,
    appearance: true,
    offers: true,
});

function toggleSection(key) {
    sectionsOpen.value[key] = !sectionsOpen.value[key];
}

const defaultPage = {
    headline: 'Quer levar isso também?',
    subheadline: 'Uma oferta exclusiva preparada para você',
    body_text: '',
    hero_image: null,
    hero_video_url: null,
    background_color: '#f3f4f6',
    background_image: null,
    show_product_just_bought: true,
};
const defaultAppearance = {
    title: 'Quer levar isso também?',
    subtitle: 'Uma oferta exclusiva preparada para você',
    primary_color: '#0ea5e9',
    button_accept: 'Sim, quero aproveitar',
    button_decline: 'Não, obrigado',
};

const form = reactive({
    page: { ...defaultPage, ...(props.config?.page || {}) },
    appearance: { ...defaultAppearance, ...(props.config?.appearance || {}) },
    products: Array.isArray(props.config?.products)
        ? props.config.products.map((p) => ({
            product_id: p.product_id ?? null,
            product_offer_id: p.product_offer_id ?? null,
            title_override: p.title_override ?? '',
            description: p.description ?? '',
            image_url: p.image_url ?? '',
            video_url: p.video_url ?? '',
          }))
        : [],
});

function productLabel(item) {
    if (!item.product_id) return 'Selecione o produto na aba Upsell do produto';
    const p = props.products_for_upsell.find((x) => x.id === item.product_id);
    if (!p) return `Produto #${item.product_id}`;
    if (item.product_offer_id) {
        const o = p.offers?.find((x) => x.id === item.product_offer_id);
        return o ? `${p.name} — ${o.name}` : p.name;
    }
    return p.name;
}

const uploadUrl = computed(() => `/produtos/${props.produto?.id}/checkout-upload`);

const saving = ref(false);
const saveError = ref('');

function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function submit() {
    saving.value = true;
    saveError.value = '';
    const payload = {
        config: {
            enabled: props.config?.enabled ?? false,
            products: form.products.map((p) => ({
                product_id: p.product_id ?? null,
                product_offer_id: p.product_offer_id ?? null,
                title_override: p.title_override || undefined,
                description: p.description || undefined,
                image_url: p.image_url || undefined,
                video_url: p.video_url || undefined,
            })),
            page: { ...form.page },
            appearance: { ...form.appearance },
        },
    };
    try {
        const res = await axios.post(
            `/produtos/${props.produto.id}/upsell-page/config`,
            payload,
            {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                validateStatus: () => true,
            }
        );
        const data = res.data;
        if (data?.success === true) {
            router.reload({ preserveScroll: true });
            return;
        }
        if (res.status === 422 && data?.errors) {
            const first = Object.values(data.errors).flat()[0];
            saveError.value = first || data.message || 'Erro de validação.';
        } else {
            saveError.value = data?.message || 'Erro ao salvar.';
        }
    } catch (e) {
        saveError.value = e.response?.data?.message || e.message || 'Erro ao salvar.';
    } finally {
        saving.value = false;
    }
}

const { setExpanded } = useSidebar();
setExpanded(false);

const inputClass =
    'block w-full rounded-xl border-2 border-zinc-200 bg-white px-4 py-2.5 text-zinc-900 placeholder-zinc-400 transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500';

</script>

<template>
    <div class="flex h-[calc(100vh-4.5rem)] min-h-0 flex-col gap-6">
        <div class="shrink-0">
            <nav class="text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
                <Link href="/produtos" class="hover:text-zinc-700 dark:hover:text-zinc-300">Produtos</Link>
                <span class="mx-1">/</span>
                <Link :href="`/produtos/${produto.id}/edit`" class="hover:text-zinc-700 dark:hover:text-zinc-300">
                    {{ produto.name }}
                </Link>
                <span class="mx-1">/</span>
                <span class="text-zinc-900 dark:text-white">Editar página upsell</span>
            </nav>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-6 lg:flex-row">
            <div class="min-h-0 w-full flex-1 space-y-4 overflow-y-auto lg:flex-none lg:shrink-0 lg:w-[380px]">
                <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left font-semibold text-zinc-900 dark:text-white"
                        @click="toggleSection('page')"
                    >
                        <span class="flex items-center gap-2">
                            <Type class="h-5 w-5 text-zinc-500" />
                            Conteúdo da página
                        </span>
                        <ChevronDown v-if="sectionsOpen.page" class="h-5 w-5 shrink-0" />
                        <ChevronRight v-else class="h-5 w-5 shrink-0" />
                    </button>
                    <div v-show="sectionsOpen.page" class="space-y-4 border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Título principal</label>
                            <input v-model="form.page.headline" type="text" :class="inputClass" placeholder="Quer levar isso também?" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Subtítulo</label>
                            <input v-model="form.page.subheadline" type="text" :class="inputClass" placeholder="Oferta especial só para você" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Texto adicional (opcional)</label>
                            <textarea v-model="form.page.body_text" rows="3" :class="inputClass" placeholder="Conteúdo extra de persuasão..." />
                        </div>
                        <div>
                            <ImageUpload
                                v-model="form.page.hero_image"
                                :upload-url="uploadUrl"
                                label="Imagem de topo (hero)"
                                recommended-size="1200×400 px"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Vídeo (URL YouTube)</label>
                            <input v-model="form.page.hero_video_url" type="url" :class="inputClass" placeholder="https://www.youtube.com/watch?v=..." />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cor de fundo</label>
                            <div class="flex gap-2">
                                <input v-model="form.page.background_color" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-600" />
                                <input v-model="form.page.background_color" type="text" :class="inputClass + ' flex-1'" />
                            </div>
                        </div>
                        <div>
                            <ImageUpload
                                v-model="form.page.background_image"
                                :upload-url="uploadUrl"
                                label="Imagem de fundo (opcional)"
                            />
                        </div>
                        <Toggle v-model="form.page.show_product_just_bought" label="Exibir bloco “Compra aprovada”" />
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left font-semibold text-zinc-900 dark:text-white"
                        @click="toggleSection('appearance')"
                    >
                        <span class="flex items-center gap-2">
                            <Palette class="h-5 w-5 text-zinc-500" />
                            Aparência e botões
                        </span>
                        <ChevronDown v-if="sectionsOpen.appearance" class="h-5 w-5 shrink-0" />
                        <ChevronRight v-else class="h-5 w-5 shrink-0" />
                    </button>
                    <div v-show="sectionsOpen.appearance" class="space-y-4 border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cor primária</label>
                            <div class="flex gap-2">
                                <input v-model="form.appearance.primary_color" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-200 p-0.5 dark:border-zinc-600" />
                                <input v-model="form.appearance.primary_color" type="text" :class="inputClass + ' flex-1'" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Texto botão aceitar</label>
                            <input v-model="form.appearance.button_accept" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Texto botão recusar</label>
                            <input v-model="form.appearance.button_decline" type="text" :class="inputClass" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left font-semibold text-zinc-900 dark:text-white"
                        @click="toggleSection('offers')"
                    >
                        <span class="flex items-center gap-2">
                            <Package class="h-5 w-5 text-zinc-500" />
                            Conteúdo por oferta
                        </span>
                        <ChevronDown v-if="sectionsOpen.offers" class="h-5 w-5 shrink-0" />
                        <ChevronRight v-else class="h-5 w-5 shrink-0" />
                    </button>
                    <div v-show="sectionsOpen.offers" class="space-y-4 border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            As ofertas são definidas na aba Upsell / Downsell do produto. Aqui você personaliza título, descrição, imagem e vídeo de cada uma.
                        </p>
                        <template v-if="form.products.length === 0">
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                                Nenhuma oferta configurada. Adicione ofertas na aba <strong>Upsell / Downsell</strong> do produto e volte aqui.
                            </p>
                        </template>
                        <div v-for="(item, idx) in form.products" :key="idx" class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/50 p-3 dark:border-zinc-600 dark:bg-zinc-800/50">
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ productLabel(item) }}</p>
                            <div>
                                <label class="mb-0.5 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Título (override)</label>
                                <input v-model="item.title_override" type="text" :class="inputClass" placeholder="Deixe vazio para usar nome do produto" />
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Descrição / copy</label>
                                <textarea v-model="item.description" rows="2" :class="inputClass" placeholder="Texto de persuasão para esta oferta" />
                            </div>
                            <div>
                                <ImageUpload
                                    v-model="item.image_url"
                                    :upload-url="uploadUrl"
                                    label="Imagem da oferta"
                                    recommended-size="400×400 px"
                                />
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Vídeo (URL)</label>
                                <input v-model="item.video_url" type="url" :class="inputClass" placeholder="https://youtube.com/..." />
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="saveError" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    {{ saveError }}
                </p>
                <Button type="button" class="w-full rounded-xl" :disabled="saving" @click="submit">
                    <Save class="mr-2 h-4 w-4" />
                    {{ saving ? 'Salvando...' : 'Salvar página upsell' }}
                </Button>
            </div>

            <div class="min-h-0 flex-1 overflow-auto rounded-2xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="p-6">
                    <p class="mb-4 text-sm font-medium text-zinc-600 dark:text-zinc-400">Preview (estático)</p>
                    <div
                        class="mx-auto max-w-2xl overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-lg"
                        :style="{ backgroundColor: form.page.background_color || '#f3f4f6' }"
                    >
                        <div v-if="form.page.hero_image" class="aspect-video w-full overflow-hidden bg-zinc-200">
                            <img :src="form.page.hero_image" alt="Hero" class="h-full w-full object-cover" />
                        </div>
                        <div v-if="form.page.hero_video_url && youtubeEmbedUrl(form.page.hero_video_url)" class="aspect-video w-full overflow-hidden bg-black">
                            <iframe
                                :src="youtubeEmbedUrl(form.page.hero_video_url)"
                                title="Vídeo"
                                class="h-full w-full"
                                :allow="YOUTUBE_IFRAME_ALLOW"
                                allowfullscreen
                            />
                        </div>
                        <div
                            class="p-6 sm:p-8"
                            :class="{ 'pt-8 sm:pt-10': form.page.hero_image || (form.page.hero_video_url && youtubeEmbedUrl(form.page.hero_video_url)) }"
                        >
                            <h2 class="mb-1 text-xl font-bold" :style="{ color: form.appearance.primary_color }">
                                {{ form.page.headline || 'Título' }}
                            </h2>
                            <p class="mb-4 text-sm text-zinc-600">
                                {{ form.page.subheadline || 'Subtítulo' }}
                            </p>
                            <p v-if="form.page.body_text" class="mb-4 whitespace-pre-wrap text-sm text-zinc-700">
                                {{ form.page.body_text }}
                            </p>
                            <div v-for="(item, idx) in form.products" :key="idx" class="mb-4 flex flex-col gap-3 rounded-xl border-2 border-zinc-200 bg-gray-50/50 p-4 sm:flex-row sm:items-center">
                                <div class="shrink-0">
                                    <img
                                        :src="item.image_url || 'https://placehold.co/96x96/e2e8f0/64748b?text=Oferta'"
                                        alt=""
                                        class="h-20 w-20 rounded-lg object-cover sm:h-24 sm:w-24"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-zinc-900">{{ item.title_override || productLabel(item) }}</h3>
                                    <p v-if="item.description" class="mt-1 text-sm text-zinc-600">{{ item.description }}</p>
                                    <div v-if="item.video_url && youtubeEmbedUrl(item.video_url)" class="mt-2 aspect-video max-w-xs overflow-hidden rounded-lg bg-black">
                                        <iframe
                                            :src="youtubeEmbedUrl(item.video_url)"
                                            title="Vídeo"
                                            class="h-full w-full"
                                            :allow="YOUTUBE_IFRAME_ALLOW"
                                            allowfullscreen
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
