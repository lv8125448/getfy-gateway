/** Formulário de pixels (compartilhado entre edição de produto e painel do afiliado). */

export function randomClientId() {
    const c = typeof globalThis !== 'undefined' ? globalThis.crypto : undefined;
    if (c && typeof c.randomUUID === 'function') {
        return c.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (ch) => {
        const r = (Math.random() * 16) | 0;
        const v = ch === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

const ENTRY_FLAGS = {
    fire_purchase_on_pix: true,
    fire_purchase_on_boleto: true,
    disable_order_bump_events: false,
};

export function newMetaEntry() {
    return { id: randomClientId(), pixel_id: '', access_token: '', ...ENTRY_FLAGS };
}
export function newTiktokEntry() {
    return { id: randomClientId(), pixel_id: '', access_token: '', ...ENTRY_FLAGS };
}
export function newGoogleAdsEntry() {
    return { id: randomClientId(), conversion_id: '', conversion_label: '', ...ENTRY_FLAGS };
}
export function newGaEntry() {
    return { id: randomClientId(), measurement_id: '', ...ENTRY_FLAGS };
}

export const DEFAULT_CONVERSION_PIXELS = {
    meta: { enabled: false, entries: [] },
    tiktok: { enabled: false, entries: [] },
    google_ads: { enabled: false, entries: [] },
    google_analytics: { enabled: false, entries: [] },
    custom_script: [],
};

export const PIXEL_TABS = [
    { id: 'meta', label: 'Meta Ads', image: '/images/pixels/meta.png' },
    { id: 'tiktok', label: 'TikTok Ads', image: '/images/pixels/tiktok.png' },
    { id: 'google_ads', label: 'Google Ads', image: '/images/pixels/googleads.png' },
    { id: 'google_analytics', label: 'Google Analytics', image: '/images/pixels/google-analytics.png' },
    { id: 'custom_script', label: 'Script personalizado', image: '/images/pixels/script.png' },
];

export function mergeConversionPixels(raw) {
    if (!raw || typeof raw !== 'object') return JSON.parse(JSON.stringify(DEFAULT_CONVERSION_PIXELS));
    const out = JSON.parse(JSON.stringify(DEFAULT_CONVERSION_PIXELS));

    function normalizeMetaLike(block, newEntryFn) {
        const enabled = !!block?.enabled;
        if (Array.isArray(block?.entries)) {
            return {
                enabled,
                entries: block.entries
                    .filter((e) => e && typeof e === 'object')
                    .map((e) => ({ ...newEntryFn(), ...e, id: e.id || randomClientId() })),
            };
        }
        if (block?.pixel_id != null || block?.access_token != null) {
            const pixel_id = String(block.pixel_id ?? '').trim();
            const access_token = String(block.access_token ?? '').trim();
            if (pixel_id || access_token) {
                return {
                    enabled,
                    entries: [
                        {
                            id: randomClientId(),
                            pixel_id,
                            access_token,
                            fire_purchase_on_pix: block.fire_purchase_on_pix !== false,
                            fire_purchase_on_boleto: block.fire_purchase_on_boleto !== false,
                            disable_order_bump_events: !!block.disable_order_bump_events,
                        },
                    ],
                };
            }
        }
        return { enabled, entries: [] };
    }

    function normalizeGoogleAdsBlock(block) {
        const enabled = !!block?.enabled;
        if (Array.isArray(block?.entries)) {
            return {
                enabled,
                entries: block.entries
                    .filter((e) => e && typeof e === 'object')
                    .map((e) => ({ ...newGoogleAdsEntry(), ...e, id: e.id || randomClientId() })),
            };
        }
        const conversion_id = String(block?.conversion_id ?? '').trim();
        if (conversion_id) {
            return {
                enabled,
                entries: [
                    {
                        id: randomClientId(),
                        conversion_id,
                        conversion_label: String(block.conversion_label ?? '').trim(),
                        fire_purchase_on_pix: block.fire_purchase_on_pix !== false,
                        fire_purchase_on_boleto: block.fire_purchase_on_boleto !== false,
                        disable_order_bump_events: !!block.disable_order_bump_events,
                    },
                ],
            };
        }
        return { enabled, entries: [] };
    }

    function normalizeGaBlock(block) {
        const enabled = !!block?.enabled;
        if (Array.isArray(block?.entries)) {
            return {
                enabled,
                entries: block.entries
                    .filter((e) => e && typeof e === 'object')
                    .map((e) => ({ ...newGaEntry(), ...e, id: e.id || randomClientId() })),
            };
        }
        const measurement_id = String(block?.measurement_id ?? '').trim();
        if (measurement_id) {
            return {
                enabled,
                entries: [
                    {
                        id: randomClientId(),
                        measurement_id,
                        fire_purchase_on_pix: block.fire_purchase_on_pix !== false,
                        fire_purchase_on_boleto: block.fire_purchase_on_boleto !== false,
                        disable_order_bump_events: !!block.disable_order_bump_events,
                    },
                ],
            };
        }
        return { enabled, entries: [] };
    }

    if (raw.meta && typeof raw.meta === 'object') {
        out.meta = normalizeMetaLike(raw.meta, newMetaEntry);
    }
    if (raw.tiktok && typeof raw.tiktok === 'object') {
        out.tiktok = normalizeMetaLike(raw.tiktok, newTiktokEntry);
    }
    if (raw.google_ads && typeof raw.google_ads === 'object') {
        out.google_ads = normalizeGoogleAdsBlock(raw.google_ads);
    }
    if (raw.google_analytics && typeof raw.google_analytics === 'object') {
        out.google_analytics = normalizeGaBlock(raw.google_analytics);
    }
    out.custom_script = Array.isArray(raw.custom_script)
        ? raw.custom_script.filter((s) => s && typeof s === 'object').map((s) => ({ id: s.id || randomClientId(), name: s.name || '', script: s.script || '' }))
        : [];
    return out;
}
