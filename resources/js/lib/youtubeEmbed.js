/**
 * Permissions-Policy delegada ao iframe do YouTube (Safari iOS exige `fullscreen` no `allow`).
 */
export const YOUTUBE_IFRAME_ALLOW =
    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen; web-share';

const PAGE_TO_EMBED_RE =
    /(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtube\.com\/v\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/;

/**
 * Converte URL de watch/share em URL de embed com parâmetros adequados para iOS.
 * @param {string} url
 * @returns {string|null}
 */
export function youtubeEmbedUrlFromPageUrl(url) {
    if (!url || typeof url !== 'string') return null;
    const trimmed = url.trim();
    if (!trimmed) return null;
    const m = trimmed.match(PAGE_TO_EMBED_RE);
    return m ? `https://www.youtube.com/embed/${m[1]}?playsinline=1&fs=1&rel=0` : null;
}

/**
 * @param {string} videoId — 11 caracteres
 * @returns {string}
 */
export function youtubeEmbedSrcFromVideoId(videoId) {
    if (!videoId || typeof videoId !== 'string' || !/^[a-zA-Z0-9_-]{11}$/.test(videoId)) return '';
    return `https://www.youtube.com/embed/${videoId}?playsinline=1&fs=1&rel=0`;
}
