/**
 * Pagar.me tokenizecard.js — fluxo oficial (checkout.pagar.me).
 * @see https://docs.pagar.me/reference/pagarme-js
 */

/** @type {string} Id do form vazio (campos usam atributo HTML form="..."). */
export const CHECKOUT_PAGARME_TOKENIZE_FORM_ID = 'checkout-pagarme-tokenize-form';

/** @type {string} Id na página ApiCheckout/Show.vue */
export const API_CHECKOUT_PAGARME_TOKENIZE_FORM_ID = 'api-checkout-pagarme-tokenize-form';

/**
 * Action do form tokenizecard: POST aqui em vez de "#" (que vira a URL do checkout GET → 405).
 * @see routes/web.php checkout.pagarme-tokenize-sink
 */
export const PAGARME_TOKENIZE_FORM_ACTION = '/checkout/pagarme-tokenize-sink';

const SCRIPT_ATTR = 'data-getfy-pagarme-tokenize';

/** @type {string|null} */
let loadedPk = null;

/** @type {boolean} */
let initDone = false;

/** @type {null | ((value: { token: string, raw: unknown }) => void)} */
let pendingResolve = null;

/** @type {null | ((err: Error) => void)} */
let pendingReject = null;

function extractToken(data) {
    if (!data || typeof data !== 'object') {
        return '';
    }
    const d = /** @type {Record<string, unknown>} */ (data);
    const keys = ['pagarmetoken', 'pagarmeToken', 'Pagarmetoken', 'id', 'token'];
    for (const k of keys) {
        const v = d[k];
        if (typeof v === 'string' && v.trim() !== '') {
            return v.trim();
        }
    }
    const card = d.card;
    if (card && typeof card === 'object') {
        const cid = /** @type {Record<string, unknown>} */ (card).id;
        if (typeof cid === 'string' && cid.trim() !== '') {
            return cid.trim();
        }
    }
    return '';
}

function failMessage(error) {
    if (error == null) {
        return 'Falha ao tokenizar o cartão.';
    }
    if (typeof error === 'string') {
        return error;
    }
    if (typeof error === 'object' && error !== null && 'message' in error && typeof error.message === 'string') {
        return error.message;
    }
    return 'Falha ao tokenizar o cartão.';
}

function attachInit() {
    if (typeof window === 'undefined' || !window.PagarmeCheckout || typeof window.PagarmeCheckout.init !== 'function') {
        throw new Error('PagarmeCheckout não está disponível.');
    }
    if (initDone) {
        return;
    }
    window.PagarmeCheckout.init(
        (data) => {
            const token = extractToken(data);
            const res = pendingResolve;
            const rej = pendingReject;
            pendingResolve = null;
            pendingReject = null;
            if (res && token) {
                res({ token, raw: data });
            } else if (rej) {
                rej(
                    new Error(
                        token
                            ? 'Sessão de tokenização expirou ou foi interrompida. Recarregue a página e tente novamente.'
                            : 'Resposta da Pagar.me sem token. Recarregue e tente novamente.'
                    )
                );
            }
            // Nunca permitir submit HTML: a rota do checkout é GET e geraria 405 Method Not Allowed.
            return false;
        },
        (error) => {
            const rej = pendingReject;
            pendingResolve = null;
            pendingReject = null;
            if (rej) {
                rej(new Error(failMessage(error)));
            }
        }
    );
    initDone = true;
}

/**
 * Remove script e permite recarregar com outra public key.
 */
export function resetPagarmeTokenizeScriptState() {
    if (typeof document === 'undefined') {
        return;
    }
    const existing = document.querySelector(`script[${SCRIPT_ATTR}]`);
    if (existing) {
        existing.remove();
    }
    loadedPk = null;
    initDone = false;
    pendingResolve = null;
    pendingReject = null;
}

/**
 * @param {string} publicKey
 * @returns {Promise<void>}
 */
export async function loadPagarmeTokenizeScript(publicKey) {
    const pk = String(publicKey || '').trim();
    if (!pk) {
        throw new Error('Chave pública Pagar.me ausente.');
    }
    if (typeof document === 'undefined') {
        throw new Error('Ambiente sem DOM.');
    }
    const existing = document.querySelector(`script[${SCRIPT_ATTR}]`);
    if (existing && loadedPk === pk && window.PagarmeCheckout) {
        return;
    }
    if (existing) {
        existing.remove();
        initDone = false;
    }
    loadedPk = pk;
    await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://checkout.pagar.me/v1/tokenizecard.js';
        s.async = true;
        s.setAttribute(SCRIPT_ATTR, '1');
        s.setAttribute('data-pagarmecheckout-app-id', pk);
        s.onload = () => resolve();
        s.onerror = () => {
            loadedPk = null;
            reject(new Error('Não foi possível carregar o script tokenizecard.js da Pagar.me.'));
        };
        document.body.appendChild(s);
    });
}

/**
 * Registra callbacks do PagarmeCheckout (uma vez). Chame após o DOM conter todos os data-pagarmecheckout-element.
 */
export function ensurePagarmeCheckoutInit() {
    attachInit();
}

/**
 * @param {string} formId
 * @param {number} [timeoutMs]
 * @returns {Promise<{ token: string, raw: unknown }>}
 */
export function requestPagarmeTokenFromForm(formId, timeoutMs = 45000) {
    return new Promise((resolve, reject) => {
        const form = typeof document !== 'undefined' ? document.getElementById(formId) : null;
        if (!form) {
            reject(new Error('Formulário de tokenização Pagar.me não encontrado.'));
            return;
        }
        const timer = setTimeout(() => {
            if (pendingReject) {
                pendingReject(new Error('Tempo esgotado ao tokenizar. Tente novamente.'));
                pendingResolve = null;
                pendingReject = null;
            }
        }, timeoutMs);

        const done = (fn) => {
            clearTimeout(timer);
            fn();
        };

        pendingResolve = (v) => {
            done(() => resolve(v));
        };
        pendingReject = (e) => {
            done(() => reject(e));
        };

        try {
            form.requestSubmit();
        } catch (e) {
            clearTimeout(timer);
            pendingResolve = null;
            pendingReject = null;
            reject(e instanceof Error ? e : new Error(String(e)));
        }
    });
}
