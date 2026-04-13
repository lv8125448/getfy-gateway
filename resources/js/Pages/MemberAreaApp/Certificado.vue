<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    certificate: { type: Object, required: true },
    recipient_name: { type: String, default: '' },
    slug: { type: String, required: true },
    certificate_available: { type: Boolean, default: false },
    progress_percent: { type: Number, default: 0 },
    completion_required_percent: { type: Number, default: 100 },
});

const printFormatOverride = ref(null);
const courseTitle = computed(() => props.certificate?.title || props.product?.name || '');
const platformName = computed(() => props.certificate?.platform_name || '');
const issuedAtLabel = computed(() => props.certificate?.issued_at_full || props.certificate?.issued_at || '');
const certPrimary = computed(() => props.certificate?.primary_color || 'var(--ma-primary)');
const certBgUrl = computed(() => props.certificate?.background_image_url || null);
const certTextColor = computed(() => props.certificate?.text_color || '#262626');
const certTitleColor = computed(() => props.certificate?.title_color || null);
const certSignatureFont = computed(() => props.certificate?.signature_font_family || 'Dancing Script');
const certSignatureFontUrl = computed(() => {
    const name = certSignatureFont.value;
    if (!name) return null;
    return `https://fonts.googleapis.com/css2?family=${encodeURIComponent(name).replace(/%20/g, '+')}&display=swap`;
});
const certOverlayEnabled = computed(() => certBgUrl.value && props.certificate?.background_overlay_enabled);
const certOverlayColor = computed(() => props.certificate?.background_overlay_color || '#000000');
const certOverlayOpacity = computed(() => {
    const raw = props.certificate?.background_overlay_opacity ?? 50;
    return (raw <= 1 ? raw * 100 : raw) / 100;
});
const certPrintFormat = computed(() => printFormatOverride.value || (props.certificate?.print_format === 'A3' ? 'A3' : 'A4'));
const printStyleText = computed(() => {
    const format = certPrintFormat.value;
    const isA3 = format === 'A3';
    const pageW = isA3 ? '420mm' : '297mm';
    const pageH = isA3 ? '297mm' : '210mm';
    const certW = isA3 ? '400mm' : '277mm';
    const certH = isA3 ? '277mm' : '190mm';
    return `
@media print {
    @page {
        size: ${pageW} ${pageH};
        margin: 0;
    }
    body, body *, .print-certificate-wrapper {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    .print-certificate-wrapper {
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: #fff !important;
    }
    .certificate-print-area {
        width: ${certW} !important;
        height: ${certH} !important;
        max-width: ${certW} !important;
        min-height: ${certH} !important;
        margin: 0 !important;
        padding: 2rem !important;
        page-break-after: avoid;
        page-break-inside: avoid;
        box-sizing: border-box !important;
    }
    .certificate-print-area .certificate-inner {
        display: flex !important;
        flex-direction: column !important;
        min-height: 100% !important;
        height: 100% !important;
        box-sizing: border-box !important;
    }
    .certificate-print-area .certificate-inner .certificate-body {
        flex: 0 0 auto !important;
    }
    .certificate-print-area .certificate-footer {
        margin-top: auto !important;
        flex-shrink: 0 !important;
    }
}
`;
});

function downloadPdf(format) {
    printFormatOverride.value = format === 'A3' ? 'A3' : 'A4';
    requestAnimationFrame(() => {
        window.print();
    });
}

function onAfterPrint() {
    printFormatOverride.value = null;
}

onMounted(() => {
    window.addEventListener('afterprint', onAfterPrint);
});
onUnmounted(() => {
    window.removeEventListener('afterprint', onAfterPrint);
});
</script>

<template>
    <div class="print-certificate-wrapper space-y-8">
        <component :is="'style'" v-if="printStyleText">{{ printStyleText }}</component>
        <link v-if="certSignatureFontUrl" rel="stylesheet" :href="certSignatureFontUrl" />
        <h1 class="text-2xl font-bold print:hidden">Certificado de conclusão</h1>
        <div
            class="certificate-print-area relative mx-auto max-w-2xl overflow-hidden rounded-2xl border border-zinc-200 p-8 shadow-md dark:border-zinc-500 print:max-w-none print:rounded-none print:shadow-none"
            :style="{
                fontFamily: certificate.font_family || 'sans-serif',
                backgroundColor: certBgUrl ? 'transparent' : '#fff',
                backgroundImage: certBgUrl ? `url(${certBgUrl})` : 'none',
                backgroundSize: certBgUrl ? 'cover' : undefined,
                backgroundPosition: certBgUrl ? 'center' : undefined,
                '--cert-primary': certPrimary,
                '--cert-text': certBgUrl ? (certificate.text_color || '#171717') : certTextColor,
                '--cert-title': certBgUrl && certificate.title_color ? certificate.title_color : certPrimary,
            }"
        >
            <!-- Overlay bloqueado: quando certificado ainda não disponível -->
            <div
                v-if="!certificate_available"
                class="pointer-events-none absolute inset-0 z-20 flex flex-col items-center justify-center rounded-2xl bg-black/60 p-6 print:hidden"
                aria-hidden="true"
            >
                <div class="flex flex-col items-center gap-3 rounded-xl bg-zinc-900/95 px-6 py-5 text-center shadow-xl">
                    <svg class="h-12 w-12 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <p class="text-lg font-semibold text-white">Certificado bloqueado</p>
                    <p class="max-w-xs text-sm text-zinc-300">
                        Complete <strong>{{ completion_required_percent }}%</strong> do curso para liberar. Seu progresso: <strong>{{ progress_percent }}%</strong>.
                    </p>
                </div>
            </div>

            <!-- Cantos em L decorativos -->
            <div class="absolute left-0 top-0 h-16 w-16 border-l-4 border-t-4 rounded-tl-lg" style="border-color: var(--cert-primary)" aria-hidden="true" />
            <div class="absolute right-0 top-0 h-16 w-16 border-r-4 border-t-4 rounded-tr-lg" style="border-color: var(--cert-primary)" aria-hidden="true" />
            <div class="absolute bottom-0 left-0 h-16 w-16 border-b-4 border-l-4 rounded-bl-lg" style="border-color: var(--cert-primary)" aria-hidden="true" />
            <div class="absolute bottom-0 right-0 h-16 w-16 border-b-4 border-r-4 rounded-br-lg" style="border-color: var(--cert-primary)" aria-hidden="true" />

            <!-- Overlay na imagem de fundo -->
            <div
                v-if="certOverlayEnabled"
                class="pointer-events-none absolute inset-0"
                style="z-index: 0"
                :style="{ backgroundColor: certOverlayColor, opacity: certOverlayOpacity }"
                aria-hidden="true"
            />

            <!-- Marca d'água -->
            <div
                class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-[0.06]"
                style="z-index: 0;"
            >
                <span
                    class="text-6xl font-bold whitespace-nowrap"
                    style="color: var(--cert-primary); transform: rotate(-35deg);"
                >
                    {{ platformName }}
                </span>
            </div>

            <div class="certificate-inner relative flex flex-col" style="z-index: 1;">
                <!-- Cabeçalho: ícone + CERTIFICADO DE CONCLUSÃO -->
                <div class="certificate-body flex flex-col items-center text-center">
                    <div class="relative flex h-14 w-14 items-center justify-center rounded-full text-[var(--cert-primary)]">
                        <div class="absolute inset-0 rounded-full" style="background-color: var(--cert-primary); opacity: 0.15" aria-hidden="true" />
                        <svg class="relative z-10 h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                    </div>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-[0.2em]" style="color: var(--cert-text)">
                        Certificado de conclusão
                    </p>
                </div>

                <!-- Título do curso -->
                <h2 class="mt-6 text-center text-2xl font-bold certificate-body" style="color: var(--cert-title)">
                    {{ courseTitle }}
                </h2>

                <!-- Bloco central -->
                <div class="certificate-body mt-8 text-center" style="color: var(--cert-text)">
                    <p>Certificamos que</p>
                    <p class="mt-2">
                        <span class="inline-block border-b-2 px-1 font-bold" style="border-color: var(--cert-primary); color: var(--cert-text)">{{ recipient_name || 'Aluno' }}</span>
                    </p>
                    <p class="mt-3">
                        completou com sucesso o curso em <strong>{{ platformName }}</strong>
                    </p>
                    <p v-if="issuedAtLabel" class="mt-2" style="color: var(--cert-text); opacity: 0.9">
                        em {{ issuedAtLabel }}
                    </p>
                    <p v-else-if="!certificate_available" class="mt-2 print:hidden" style="color: var(--cert-text); opacity: 0.8">
                        em --
                    </p>
                </div>

                <!-- Rodapé em duas colunas -->
                <div class="certificate-footer mt-12 grid grid-cols-2 gap-8 border-t pt-8" style="border-color: rgba(0,0,0,0.12); color: var(--cert-text)">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide" style="opacity: 0.85">Assinatura do Instrutor</p>
                        <p class="mt-1 font-medium" :style="{ fontFamily: certSignatureFont, color: 'var(--cert-text)' }">{{ certificate.signature_text || 'Instrutor' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold">{{ platformName }}</p>
                        <p class="text-sm" style="opacity: 0.85">Plataforma de Cursos</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap justify-center gap-4 print:hidden">
            <template v-if="certificate_available">
                <button
                    type="button"
                    class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:opacity-95"
                    style="background-color: var(--ma-primary)"
                    @click="downloadPdf('A4')"
                >
                    Salvar PDF A4
                </button>
                <button
                    type="button"
                    class="rounded-xl border-2 px-5 py-2.5 text-sm font-semibold transition hover:opacity-95"
                    style="border-color: var(--ma-primary); color: var(--ma-primary)"
                    @click="downloadPdf('A3')"
                >
                    Salvar PDF A3
                </button>
            </template>
            <Link :href="`/m/${slug}`" class="rounded-xl border-2 px-5 py-2.5 text-sm font-medium transition hover:opacity-90" style="border-color: var(--ma-primary); color: var(--ma-primary)">
                Voltar à área de membros
            </Link>
        </div>
    </div>
</template>
