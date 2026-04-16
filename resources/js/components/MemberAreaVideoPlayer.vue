<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { getVideoProviderType } from '@/lib/utils';
import { Maximize2 } from 'lucide-vue-next';

const props = defineProps({
    src: { type: String, default: '' },
    poster: { type: String, default: '' },
    playsinline: { type: Boolean, default: true },
    watermarkEnabled: { type: Boolean, default: false },
    watermarkData: { type: Object, default: null },
});

const emit = defineEmits(['ended']);

const watermarkPosition = ref(0);
let watermarkInterval = null;

const POSITIONS = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];

const providerType = computed(() => getVideoProviderType(props.src));
/** YouTube/Vimeo no iOS: Fullscreen API no player inteiro falha; Vidstack usa fullscreen no iframe do provider. */
const isEmbedProvider = computed(() => {
    const t = providerType.value;
    return t === 'youtube' || t === 'vimeo';
});
const isMobile = ref(false);
const isIphoneSafari = ref(false);
let mobileMql = null;
function onMobileQueryChange(e) {
    isMobile.value = !!e.matches;
}
const playerRef = ref(null);
let onFullscreenChangeHandler = null;

function detectIphoneSafari() {
    if (typeof navigator === 'undefined') return false;
    const ua = String(navigator.userAgent || '');
    // iPhone Safari: exclui browsers iOS com UA próprio (Chrome/Firefox/Edge) e webviews comuns.
    const isIphone = /\biPhone\b/i.test(ua);
    const isSafari = /Safari/i.test(ua) && !/(CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo)/i.test(ua);
    const isWebView = /(FBAN|FBAV|Instagram|Line|WhatsApp|GSA)/i.test(ua) || (!/Safari/i.test(ua) && /AppleWebKit/i.test(ua));
    return isIphone && isSafari && !isWebView;
}

async function lockOrientationLandscape() {
    try {
        if (typeof screen === 'undefined') return;
        if (!screen.orientation || typeof screen.orientation.lock !== 'function') return;
        await screen.orientation.lock('landscape');
    } catch (_) {}
}
function unlockOrientation() {
    try {
        if (typeof screen === 'undefined') return;
        if (!screen.orientation || typeof screen.orientation.unlock !== 'function') return;
        screen.orientation.unlock();
    } catch (_) {}
}
function isPlayerFullscreen() {
    if (typeof document === 'undefined') return false;
    const el = playerRef.value;
    if (!el) return false;
    const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
    if (!fsEl) return false;
    return fsEl === el || (typeof el.contains === 'function' && el.contains(fsEl));
}

// Vidstack 1.x aceita URL completa (YouTube, Vimeo ou nativo) no src do media-player
const vidstackSrc = computed(() => {
    if (!props.src || !props.src.trim()) return '';
    const u = props.src.trim();
    const type = providerType.value;
    if (type === 'youtube') {
        const m = u.match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
        return m ? `youtube/${m[1]}` : u;
    }
    if (type === 'vimeo') {
        const m = u.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        return m ? `vimeo/${m[1]}` : u;
    }
    return u;
});

// Para YouTube: usar thumbnail como poster quando não houver poster customizado, assim o botão do YouTube não aparece no centro
const posterUrl = computed(() => {
    if (props.poster) return props.poster;
    if (providerType.value !== 'youtube' || !props.src) return '';
    const m = props.src.trim().match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
    if (!m) return '';
    const id = m[1];
    return `https://img.youtube.com/vi/${id}/sddefault.jpg`;
});

const watermarkText = computed(() => {
    if (!props.watermarkEnabled || !props.watermarkData) return '';
    const d = props.watermarkData;
    const name = (d.name ?? '').trim() || 'Aluno';
    if (d.cpf && String(d.cpf).trim()) {
        return `${name} - ${String(d.cpf).trim()}`;
    }
    return (d.email && String(d.email).trim()) ? `${name} - ${String(d.email).trim()}` : name;
});

onMounted(() => {
    isIphoneSafari.value = detectIphoneSafari();
    if (typeof window !== 'undefined' && 'matchMedia' in window) {
        mobileMql = window.matchMedia('(max-width: 768px)');
        isMobile.value = !!mobileMql.matches;
        try {
            mobileMql.addEventListener('change', onMobileQueryChange);
        } catch (_) {
            try {
                mobileMql.addListener(onMobileQueryChange);
            } catch (_) {}
        }
    }
    if (typeof document !== 'undefined') {
        onFullscreenChangeHandler = () => {
            if (!isMobile.value) return;
            if (isPlayerFullscreen()) {
                setTimeout(() => lockOrientationLandscape(), 0);
            } else {
                unlockOrientation();
            }
        };
        document.addEventListener('fullscreenchange', onFullscreenChangeHandler);
        document.addEventListener('webkitfullscreenchange', onFullscreenChangeHandler);
    }
    if (props.watermarkEnabled && watermarkText.value) {
        watermarkInterval = setInterval(() => {
            watermarkPosition.value = (watermarkPosition.value + 1) % POSITIONS.length;
        }, 20000);
    }
});
onUnmounted(() => {
    if (watermarkInterval) clearInterval(watermarkInterval);
    if (typeof document !== 'undefined' && onFullscreenChangeHandler) {
        document.removeEventListener('fullscreenchange', onFullscreenChangeHandler);
        document.removeEventListener('webkitfullscreenchange', onFullscreenChangeHandler);
        onFullscreenChangeHandler = null;
    }
    unlockOrientation();
    if (mobileMql) {
        try {
            mobileMql.removeEventListener('change', onMobileQueryChange);
        } catch (_) {
            try {
                mobileMql.removeListener(onMobileQueryChange);
            } catch (_) {}
        }
    }
});

const effectivePlaysinline = computed(() => {
    if (providerType.value !== 'native') return props.playsinline;
    if (props.playsinline === false) return false;
    return !isMobile.value;
});

const showFullscreenOverlay = computed(() => {
    // iPhone Safari + YouTube: botão de fullscreen pode não aparecer no layout.
    return isIphoneSafari.value && isMobile.value && providerType.value === 'youtube' && !!props.src;
});

async function requestProviderFullscreen() {
    const el = playerRef.value;
    if (!el) return;
    try {
        // Vidstack 1.x: método no elemento <media-player>.
        if (typeof el.enterFullscreen === 'function') {
            await el.enterFullscreen('provider');
            return;
        }
        // Fallback: evento (caso a instância não exponha o método).
        el.dispatchEvent(new CustomEvent('media-enter-fullscreen-request', { bubbles: true, composed: true }));
    } catch (_) {
        // Silencioso: no iOS o provider pode bloquear a request fora de gesto.
    }
}

function onEnded() {
    emit('ended');
}

function onContextMenu(e) {
    e.preventDefault();
}
</script>

<template>
    <div
        class="member-area-video-player aspect-video w-full overflow-hidden rounded-lg bg-black relative"
        @contextmenu.prevent="onContextMenu"
    >
        <button
            v-if="showFullscreenOverlay"
            type="button"
            class="fullscreen-overlay-btn"
            aria-label="Tela cheia"
            @click.stop.prevent="requestProviderFullscreen"
        >
            <Maximize2 class="h-4 w-4" aria-hidden="true" />
            <span class="sr-only">Tela cheia</span>
        </button>
        <media-player
            v-if="src"
            ref="playerRef"
            class="player"
            :src="vidstackSrc"
            :poster="posterUrl"
            :playsinline="effectivePlaysinline"
            :fullscreen-target="isEmbedProvider ? 'provider' : undefined"
            crossorigin
            @vds-ended="onEnded"
            @vds-end="onEnded"
        >
            <media-provider>
                <media-poster v-if="posterUrl" class="vds-poster" :src="posterUrl" alt="" />
            </media-provider>
            <media-video-layout>
                <media-airplay-button slot="airPlayButton">
                    <media-icon type="airplay" />
                </media-airplay-button>
                <media-google-cast-button slot="googleCastButton">
                    <media-icon type="chromecast" />
                </media-google-cast-button>
            </media-video-layout>
        </media-player>
        <div
            v-if="watermarkEnabled && watermarkText"
            class="watermark-overlay"
            :class="POSITIONS[watermarkPosition]"
        >
            {{ watermarkText }}
        </div>
    </div>
</template>

<style scoped>
.member-area-video-player {
    --media-brand: #f5f5f5;
    --media-focus-ring-color: #4e9cf6;
}
.player {
    width: 100%;
    height: 100%;
    display: block;
}
.player[data-view-type='video'] {
    aspect-ratio: 16 / 9;
}
/* iPhone Safari (YouTube): botão overlay p/ fullscreen do provider */
.fullscreen-overlay-btn {
    position: absolute;
    right: 10px;
    bottom: 10px;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 36px;
    width: 36px;
    border-radius: 9999px;
    background: rgba(0, 0, 0, 0.55);
    color: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
}
.fullscreen-overlay-btn:active {
    transform: scale(0.98);
}
.fullscreen-overlay-btn:focus-visible {
    outline: 2px solid rgba(78, 156, 246, 0.9);
    outline-offset: 2px;
}
/* Poster por cima do iframe do YouTube até o usuário dar play */
.player :deep(.vds-poster),
.player :deep([data-media-poster]) {
    z-index: 1;
}
.player :deep(media-provider),
.player :deep([data-media-provider]) {
    z-index: 0;
}
/* Camada 1: esconder PiP para dificultar gravação */
.player :deep(media-pip-button) {
    display: none !important;
}
/* Marca d'água: overlay que muda de posição */
.watermark-overlay {
    position: absolute;
    z-index: 2;
    pointer-events: none;
    font-size: clamp(0.75rem, 2vw, 1rem);
    color: rgba(255, 255, 255, 0.6);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
    transition: left 0.5s, top 0.5s, right 0.5s, bottom 0.5s;
}
.watermark-overlay.top-left {
    left: 8px;
    top: 8px;
}
.watermark-overlay.top-right {
    right: 8px;
    top: 8px;
}
.watermark-overlay.bottom-left {
    left: 8px;
    bottom: 8px;
}
.watermark-overlay.bottom-right {
    right: 8px;
    bottom: 8px;
}
.watermark-overlay.center {
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
}
</style>
