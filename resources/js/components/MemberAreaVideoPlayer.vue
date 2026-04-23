<script setup>
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { getVideoProviderType } from '@/lib/utils';
import { Maximize2, Play, Pause, Settings } from 'lucide-vue-next';

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
const isYoutube = computed(() => providerType.value === 'youtube' && !!props.src);
const isMobile = ref(false);
const isIphoneSafari = ref(false);
let mobileMql = null;
function onMobileQueryChange(e) {
    isMobile.value = !!e.matches;
}
const playerRef = ref(null);
let onFullscreenChangeHandler = null;

const youtubeVideoId = computed(() => {
    if (!props.src) return null;
    const u = props.src.trim();
    const m = u.match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
    return m?.[1] ?? null;
});

const hasYoutubePlaylist = computed(() => {
    if (!props.src) return false;
    try {
        const u = new URL(props.src, typeof window !== 'undefined' ? window.location.origin : 'https://example.com');
        return u.searchParams.has('list') || u.searchParams.has('playlist');
    } catch (_) {
        return /[?&]list=/.test(props.src);
    }
});

// ---------------------------------------------------------------------------
// YouTube legacy player (IFrame API) with quality selector.
// ---------------------------------------------------------------------------
const youtubeMountEl = ref(null);
let ytPlayer = null;
let ytApiPromise = null;
let ytApplyQualityTimer = null;
let ytProgressTimer = null;
let ytControlsHideTimer = null;

const QUALITY_STORAGE_KEY = 'member-area-youtube-quality';
const QUALITY_LABELS = {
    auto: 'Auto',
    small: '240p',
    medium: '360p',
    large: '480p',
    hd720: '720p',
    hd1080: '1080p',
    highres: 'Alta',
};

const qualityMenuOpen = ref(false);
const selectedQuality = ref('auto');
const lastQualityError = ref(null);
const ytIsPlaying = ref(false);
const ytCurrentTime = ref(0);
const ytDuration = ref(0);
const ytControlsVisible = ref(true);
const ytRootEl = ref(null);
const ytPosterVisible = ref(true);
const ytScrubbing = ref(false);
const ytMaskActive = ref(false);
let ytMaskTimer = null;

const ytMaskBranding = computed(() => {
    if (ytPosterVisible.value) return true;
    if (ytScrubbing.value) return true;
    return ytMaskActive.value;
});

function loadYoutubeApiOnce() {
    if (typeof window === 'undefined') return Promise.reject(new Error('no_window'));
    if (ytApiPromise) return ytApiPromise;
    ytApiPromise = new Promise((resolve, reject) => {
        if (window.YT?.Player) {
            resolve(window.YT);
            return;
        }
        const existing = document.querySelector('script[data-yt-iframe-api]');
        if (!existing) {
            const s = document.createElement('script');
            s.src = 'https://www.youtube.com/iframe_api';
            s.async = true;
            s.defer = true;
            s.dataset.ytIframeApi = '1';
            s.onerror = () => reject(new Error('yt_iframe_api_load_failed'));
            document.head.appendChild(s);
        }
        const prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = function () {
            try {
                if (typeof prev === 'function') prev();
            } catch (_) {}
            if (window.YT?.Player) resolve(window.YT);
            else reject(new Error('yt_iframe_api_ready_but_missing'));
        };
    });
    return ytApiPromise;
}

function getSavedQuality() {
    try {
        const q = localStorage.getItem(QUALITY_STORAGE_KEY);
        return q && typeof q === 'string' ? q : 'auto';
    } catch (_) {
        return 'auto';
    }
}
function saveQuality(q) {
    try {
        localStorage.setItem(QUALITY_STORAGE_KEY, q);
    } catch (_) {}
}

function destroyYoutubePlayer() {
    if (ytApplyQualityTimer) {
        clearTimeout(ytApplyQualityTimer);
        ytApplyQualityTimer = null;
    }
    if (ytProgressTimer) {
        clearInterval(ytProgressTimer);
        ytProgressTimer = null;
    }
    if (ytControlsHideTimer) {
        clearTimeout(ytControlsHideTimer);
        ytControlsHideTimer = null;
    }
    try {
        if (ytPlayer && typeof ytPlayer.destroy === 'function') ytPlayer.destroy();
    } catch (_) {}
    ytPlayer = null;
    qualityMenuOpen.value = false;
    lastQualityError.value = null;
    ytIsPlaying.value = false;
    ytCurrentTime.value = 0;
    ytDuration.value = 0;
    ytControlsVisible.value = true;
    ytPosterVisible.value = true;
    ytScrubbing.value = false;
    ytMaskActive.value = false;
    if (ytMaskTimer) {
        clearTimeout(ytMaskTimer);
        ytMaskTimer = null;
    }
}

function maskBrandingFor(ms = 450) {
    ytMaskActive.value = true;
    if (ytMaskTimer) clearTimeout(ytMaskTimer);
    ytMaskTimer = setTimeout(() => {
        ytMaskActive.value = false;
        ytMaskTimer = null;
    }, Math.max(0, ms));
}

function applyYoutubeQuality(q) {
    lastQualityError.value = null;
    if (!ytPlayer) return;
    if (q === 'auto') {
        // YouTube não expõe auto-select via API moderna; tentar resetar para default.
        try {
            if (typeof ytPlayer.setPlaybackQuality === 'function') ytPlayer.setPlaybackQuality('default');
        } catch (_) {}
        return;
    }

    try {
        if (typeof ytPlayer.setPlaybackQuality === 'function') {
            ytPlayer.setPlaybackQuality(q);
            return;
        }
        if (typeof ytPlayer.setPlaybackQualityRange === 'function') {
            ytPlayer.setPlaybackQualityRange(q);
            return;
        }
        lastQualityError.value = 'quality_api_unavailable';
    } catch (e) {
        lastQualityError.value = 'quality_set_failed';
    }
}

async function initYoutubePlayer() {
    destroyYoutubePlayer();
    if (!isYoutube.value || !youtubeVideoId.value) return;
    await nextTick();
    const mount = youtubeMountEl.value;
    if (!mount) return;

    selectedQuality.value = getSavedQuality();
    await loadYoutubeApiOnce();
    if (!window.YT?.Player) return;

    const mountId = `yt-legacy-${Math.random().toString(36).slice(2, 10)}`;
    mount.innerHTML = `<div id="${mountId}" class="yt-legacy-iframe"></div>`;

    ytPlayer = new window.YT.Player(mountId, {
        videoId: youtubeVideoId.value,
        host: 'https://www.youtube-nocookie.com',
        playerVars: {
            autoplay: 0,
            // Controls nativos exibem marca/overlays do YouTube; usamos controles próprios para manter UI limpa.
            controls: 0,
            playsinline: 1,
            rel: 0,
            modestbranding: 1,
            iv_load_policy: 3,
            disablekb: 1,
            fs: 0,
            // Evita inicializar como playlist mesmo se URL original tiver `list=...`.
            list: undefined,
            listType: undefined,
        },
        events: {
            onReady: () => {
                // Aplicar qualidade em diferentes momentos melhora a chance de pegar (como na antiga).
                applyYoutubeQuality(selectedQuality.value);
                ytApplyQualityTimer = setTimeout(() => applyYoutubeQuality(selectedQuality.value), 800);

                // Iniciar polling de progresso (API não emite eventos de timeupdate).
                ytProgressTimer = setInterval(() => {
                    try {
                        if (!ytPlayer) return;
                        const d = ytPlayer.getDuration?.();
                        if (typeof d === 'number' && d > 0) ytDuration.value = d;
                        const t = ytPlayer.getCurrentTime?.();
                        if (typeof t === 'number' && t >= 0) ytCurrentTime.value = t;
                    } catch (_) {}
                }, 350);
            },
            onStateChange: (e) => {
                // PLAYING
                if (e?.data === window.YT.PlayerState?.PLAYING) {
                    ytIsPlaying.value = true;
                    ytPosterVisible.value = false;
                    scheduleHideControls();
                    if (ytApplyQualityTimer) clearTimeout(ytApplyQualityTimer);
                    ytApplyQualityTimer = setTimeout(() => applyYoutubeQuality(selectedQuality.value), 500);
                }
                if (e?.data === window.YT.PlayerState?.PAUSED) {
                    ytIsPlaying.value = false;
                    ytControlsVisible.value = true;
                }
                // ENDED
                if (e?.data === window.YT.PlayerState?.ENDED) {
                    ytIsPlaying.value = false;
                    ytControlsVisible.value = true;
                    ytPosterVisible.value = true;
                    onEnded();
                }
            },
        },
    });
}

function setQuality(q) {
    selectedQuality.value = q;
    saveQuality(q);
    qualityMenuOpen.value = false;
    // aplicar agora e tentar novamente após um curto delay
    applyYoutubeQuality(q);
    if (ytApplyQualityTimer) clearTimeout(ytApplyQualityTimer);
    ytApplyQualityTimer = setTimeout(() => applyYoutubeQuality(q), 600);
}

function togglePlay() {
    if (!ytPlayer) return;
    try {
        const state = ytPlayer.getPlayerState?.();
        if (state === window.YT?.PlayerState?.PLAYING) {
            ytPlayer.pauseVideo?.();
            ytIsPlaying.value = false;
            // Pode piscar overlays do YouTube ao pausar.
            maskBrandingFor(450);
        } else {
            ytPlayer.playVideo?.();
            ytIsPlaying.value = true;
            // Pode piscar overlays do YouTube ao dar play.
            maskBrandingFor(450);
        }
    } catch (_) {}
}

const ytProgressPct = computed(() => {
    const d = ytDuration.value || 0;
    if (d <= 0) return 0;
    return Math.max(0, Math.min(100, (ytCurrentTime.value / d) * 100));
});

function formatTime(seconds) {
    const s = Math.max(0, Math.floor(Number(seconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
    return `${m}:${String(sec).padStart(2, '0')}`;
}

function seekToPct(pct) {
    if (!ytPlayer) return;
    const d = ytDuration.value || 0;
    if (d <= 0) return;
    const t = (Math.max(0, Math.min(100, pct)) / 100) * d;
    try {
        ytPlayer.seekTo?.(t, true);
        ytCurrentTime.value = t;
        // Mascara branding que pode piscar após seek.
        maskBrandingFor(450);
    } catch (_) {}
}

function onYoutubeOverlayInteract() {
    // Mostra/fecha menu com interação no overlay (não no iframe).
    if (qualityMenuOpen.value) {
        qualityMenuOpen.value = false;
    }
}

function onScrubStart() {
    ytScrubbing.value = true;
    showControls();
}
function onScrubEnd() {
    ytScrubbing.value = false;
    maskBrandingFor(350);
    scheduleHideControls();
}

function scheduleHideControls() {
    if (ytControlsHideTimer) clearTimeout(ytControlsHideTimer);
    if (!ytIsPlaying.value) {
        ytControlsVisible.value = true;
        return;
    }
    ytControlsHideTimer = setTimeout(() => {
        ytControlsVisible.value = false;
    }, 2200);
}

function showControls() {
    ytControlsVisible.value = true;
    scheduleHideControls();
}

async function requestYoutubeFullscreen() {
    const el = ytRootEl.value;
    if (!el) return;
    try {
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            if (document.exitFullscreen) await document.exitFullscreen();
            else if (document.webkitExitFullscreen) await document.webkitExitFullscreen();
            return;
        }
        if (el.requestFullscreen) await el.requestFullscreen();
        else if (el.webkitRequestFullscreen) await el.webkitRequestFullscreen();
    } catch (_) {}
}

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
        // YouTube embed não permite forçar qualidade via API; `vq` é best-effort para reduzir casos de começar muito baixo.
        return m ? `youtube/${m[1]}?vq=hd1080&playsinline=1&rel=0&modestbranding=1` : u;
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

    initYoutubePlayer();
});
onUnmounted(() => {
    if (watermarkInterval) clearInterval(watermarkInterval);
    destroyYoutubePlayer();
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

watch(
    () => [providerType.value, youtubeVideoId.value],
    () => {
        if (providerType.value === 'youtube') initYoutubePlayer();
        else destroyYoutubePlayer();
    }
);

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
        <div
            v-if="isYoutube"
            ref="ytRootEl"
            class="yt-legacy-root"
            @mousemove="showControls"
            @touchstart.passive="showControls"
        >
            <div ref="youtubeMountEl" class="yt-legacy-mount" />
            <!-- Poster/máscara: esconde thumb/logo do YouTube antes do primeiro play e durante scrub/seek -->
            <div v-if="ytMaskBranding" class="yt-mask" aria-hidden="true">
                <div
                    v-if="ytPosterVisible && posterUrl"
                    class="yt-poster"
                    :style="{ backgroundImage: `url('${posterUrl}')` }"
                />
            </div>
            <!-- Camada por cima do iframe para bloquear UI/overlays do YouTube (logo, menus, playlist). -->
            <button
                type="button"
                class="yt-veil"
                aria-label="Reproduzir/pausar vídeo"
                @click.stop.prevent="togglePlay"
                @pointerdown="onYoutubeOverlayInteract"
                @touchstart.passive="onYoutubeOverlayInteract"
            />

            <!-- Barra de progresso: largura total do vídeo -->
            <div
                class="yt-progress-overlay"
                :class="{ hidden: !ytControlsVisible && !ytScrubbing }"
                @pointerdown.stop="onScrubStart"
                @pointerup.stop="onScrubEnd"
                @pointercancel.stop="onScrubEnd"
                @touchend.stop="onScrubEnd"
            >
                <input
                    class="yt-progress-overlay-range"
                    type="range"
                    min="0"
                    max="100"
                    step="0.1"
                    :value="ytProgressPct"
                    @input="seekToPct(parseFloat($event.target.value))"
                    aria-label="Progresso do vídeo"
                />
            </div>

            <div class="yt-legacy-controls" :class="{ hidden: !ytControlsVisible }" @pointerdown.stop>
                <div class="yt-controlbar">
                    <button type="button" class="yt-icon-btn" aria-label="Play/Pause" @click="togglePlay">
                        <Pause v-if="ytIsPlaying" class="h-4 w-4" aria-hidden="true" />
                        <Play v-else class="h-4 w-4" aria-hidden="true" />
                    </button>

                    <div class="yt-time">
                        {{ formatTime(ytCurrentTime) }} <span class="yt-time-sep">/</span> {{ formatTime(ytDuration) }}
                    </div>

                    <button type="button" class="yt-icon-btn" aria-label="Tela cheia" @click="requestYoutubeFullscreen">
                        <Maximize2 class="h-4 w-4" aria-hidden="true" />
                    </button>

                    <div class="yt-menu-wrap">
                        <button type="button" class="yt-icon-btn" aria-label="Qualidade" @click="qualityMenuOpen = !qualityMenuOpen">
                            <Settings class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <div v-if="qualityMenuOpen" class="yt-quality-menu" role="menu" aria-label="Qualidade do vídeo">
                            <button type="button" class="yt-quality-item" :class="{ active: selectedQuality === 'auto' }" @click="setQuality('auto')">Auto</button>
                            <button type="button" class="yt-quality-item" :class="{ active: selectedQuality === 'medium' }" @click="setQuality('medium')">360p</button>
                            <button type="button" class="yt-quality-item" :class="{ active: selectedQuality === 'large' }" @click="setQuality('large')">480p</button>
                            <button type="button" class="yt-quality-item" :class="{ active: selectedQuality === 'hd720' }" @click="setQuality('hd720')">720p</button>
                            <button type="button" class="yt-quality-item" :class="{ active: selectedQuality === 'hd1080' }" @click="setQuality('hd1080')">1080p</button>
                        </div>
                    </div>
                </div>

                <div v-if="lastQualityError" class="yt-quality-error">
                    Não foi possível aplicar esta qualidade neste dispositivo.
                </div>
                <div v-if="hasYoutubePlaylist" class="yt-playlist-warning">
                    Este link do YouTube contém playlist; o player ignora a playlist.
                </div>
            </div>
        </div>

        <media-player
            v-else-if="src"
            ref="playerRef"
            class="player"
            :src="vidstackSrc"
            :poster="posterUrl"
            :playsinline="effectivePlaysinline"
            :fullscreen-target="isEmbedProvider ? 'provider' : undefined"
            load="eager"
            preload="auto"
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

.yt-legacy-root,
.yt-legacy-mount,
.yt-legacy-iframe {
    width: 100%;
    height: 100%;
}
.yt-legacy-root {
    position: relative;
}
.yt-legacy-mount :deep(iframe) {
    width: 100% !important;
    height: 100% !important;
    display: block;
}
.yt-mask {
    position: absolute;
    inset: 0;
    z-index: 1;
    background: #000;
}
.yt-poster {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    opacity: 0.98;
}
.yt-veil {
    position: absolute;
    inset: 0;
    z-index: 2;
    background: transparent;
    border: 0;
    padding: 0;
    margin: 0;
    cursor: pointer;
}
.yt-progress-overlay {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 3;
    padding: 10px 12px;
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.yt-progress-overlay.hidden {
    opacity: 0;
    transform: translateY(6px);
    pointer-events: none;
}
.yt-progress-overlay-range {
    width: 100%;
    height: 4px;
    accent-color: rgba(255, 255, 255, 0.92);
}
.yt-legacy-controls {
    position: absolute;
    left: 10px;
    bottom: 38px;
    z-index: 3;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: opacity 0.18s ease, transform 0.18s ease;
}
.yt-legacy-controls.hidden {
    opacity: 0;
    transform: translateY(6px);
    pointer-events: none;
}
.yt-controlbar {
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 16px;
    background: rgba(0, 0, 0, 0.55);
    color: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(6px);
}
.yt-icon-btn {
    height: 34px;
    width: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.92);
    transition: background 0.15s ease, transform 0.1s ease;
}
.yt-icon-btn:hover {
    background: rgba(255, 255, 255, 0.16);
}
.yt-icon-btn:active {
    transform: scale(0.98);
}
.yt-time {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.86);
    font-variant-numeric: tabular-nums;
    user-select: none;
}
.yt-time-sep {
    opacity: 0.6;
    padding: 0 4px;
}
.yt-menu-wrap {
    position: relative;
}
.yt-quality-menu {
    pointer-events: auto;
    width: 180px;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.72);
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    padding: 6px;
    display: grid;
    gap: 4px;
    position: absolute;
    right: 0;
    bottom: calc(100% + 10px);
}
.yt-quality-item {
    width: 100%;
    text-align: left;
    padding: 8px 10px;
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.92);
    font-size: 12px;
    line-height: 1;
    background: transparent;
    border: 1px solid transparent;
}
.yt-quality-item.active {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.18);
}
.yt-quality-error {
    pointer-events: none;
    font-size: 11px;
    color: rgba(255, 200, 200, 0.95);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
}
.yt-playlist-warning {
    pointer-events: none;
    font-size: 11px;
    color: rgba(255, 255, 255, 0.7);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
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
