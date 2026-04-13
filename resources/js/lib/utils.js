import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

/**
 * Formata valor monetário em formato compacto (K = mil, M = milhão).
 * Ex: 1300 → 1.3K, 10000 → 10K, 1500000 → 1.5M
 * @param {number} value - Valor em reais
 * @returns {string}
 */
export function formatCompactCurrency(value) {
    const n = Number(value) || 0;
    if (n >= 1_000_000) {
        const m = n / 1_000_000;
        return (m % 1 === 0 ? m : m.toFixed(1)) + 'M';
    }
    if (n >= 1_000) {
        const k = n / 1_000;
        return (k % 1 === 0 ? k : k.toFixed(1)) + 'K';
    }
    return String(Math.round(n));
}

/**
 * Detecta o tipo de provedor de vídeo a partir da URL (para escolher player Vidstack vs iframe).
 * @param {string} url - URL do vídeo
 * @returns {'youtube'|'vimeo'|'native'}
 */
export function getVideoProviderType(url) {
    if (!url || typeof url !== 'string') return 'native';
    const u = url.trim();
    if (/^(https?:\/\/)?(www\.|m\.)?youtube\.com\/watch\?.*v=/i.test(u)) return 'youtube';
    if (/^(https?:\/\/)?youtu\.be\//i.test(u)) return 'youtube';
    if (/youtube\.com\/embed\//i.test(u)) return 'youtube';
    if (/vimeo\.com\/(?:video\/)?(\d+)/i.test(u)) return 'vimeo';
    if (/player\.vimeo\.com\/video\//i.test(u)) return 'vimeo';
    return 'native';
}

/**
 * Converte URL de vídeo (YouTube, Vimeo) para formato embed, permitindo exibir em iframe.
 * @param {string} url - URL do vídeo (ex: youtube.com/watch?v=ID ou youtu.be/ID)
 * @returns {string} URL para usar no src do iframe, ou a própria url se não for conversível
 */
export function videoEmbedUrl(url) {
    if (!url || typeof url !== 'string') return url || '';
    const u = url.trim();
    // YouTube: watch?v=ID, youtu.be/ID, embed/ID
    const ytWatch = u.match(/^(https?:\/\/)?(www\.|m\.)?youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]+)/);
    if (ytWatch) return `https://www.youtube.com/embed/${ytWatch[3]}`;
    const ytShort = u.match(/^(https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]+)/);
    if (ytShort) return `https://www.youtube.com/embed/${ytShort[2]}`;
    if (/youtube\.com\/embed\//i.test(u)) return u;
    // Vimeo: vimeo.com/ID ou player.vimeo.com/video/ID
    const vimeo = u.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`;
    if (/player\.vimeo\.com\/video\//i.test(u)) return u;
    return u;
}

/**
 * Formata texto de descrição de aula: preserva quebras de linha e transforma URLs em links.
 * Escapa HTML para evitar XSS.
 * @param {string} text - Texto puro (pode conter \n e URLs)
 * @returns {string} HTML seguro para usar com v-html
 */
export function formatLessonDescription(text) {
    if (text == null || typeof text !== 'string') return '';
    let s = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    s = s.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    s = s.replace(/\n/g, '<br>\n');
    const urlRegex = /(https?:\/\/[^\s<>"']+)|(www\.[^\s<>"']+\.[^\s<>"']+)/gi;
    s = s.replace(urlRegex, (match) => {
        const href = match.startsWith('www.') ? `https://${match}` : match;
        const cleanHref = href.replace(/[.,;:!?)]+$/, '');
        return `<a href="${cleanHref.replace(/"/g, '&quot;')}" target="_blank" rel="noopener noreferrer" class="text-[var(--ma-primary)] hover:underline">${match}</a>`;
    });
    return s;
}
