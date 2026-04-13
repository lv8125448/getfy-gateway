import { ref, computed, watch } from 'vue';

const LOCALE_KEY = 'checkout_locale';
const CURRENCY_KEY = 'checkout_currency';
const SUPPORTED_LOCALES = ['pt_BR', 'en', 'es'];

/**
 * @param {Object} options
 * @param {Record<string, Record<string, string>>} options.translations - checkout_translations
 * @param {Array<{ code: string, symbol: string, label: string, rate_to_brl: number }>} options.currencies
 * @param {string} [options.suggestedLocale] - suggested_locale from backend
 * @param {string} [options.suggestedCurrency] - suggested_currency from backend
 * @param {string} [options.storageKey] - e.g. checkout_slug for localStorage keys
 */
export function useCheckoutLocale(options = {}) {
    const {
        translations = {},
        currencies = [],
        suggestedLocale = 'pt_BR',
        suggestedCurrency = 'BRL',
        storageKey = 'default',
    } = options;

    const localeStorageKey = `${LOCALE_KEY}_${storageKey}`;
    const currencyStorageKey = `${CURRENCY_KEY}_${storageKey}`;

    function getStoredLocale() {
        try {
            const v = localStorage.getItem(localeStorageKey);
            return SUPPORTED_LOCALES.includes(v) ? v : null;
        } catch {
            return null;
        }
    }

    function getStoredCurrency() {
        try {
            const v = localStorage.getItem(currencyStorageKey);
            const codes = currencies.map((c) => c.code);
            return v && codes.includes(v) ? v : null;
        } catch {
            return null;
        }
    }

    const locale = ref(getStoredLocale() || suggestedLocale || 'pt_BR');
    const currency = ref(getStoredCurrency() || suggestedCurrency || 'BRL');

    watch(
        locale,
        (v) => {
            try {
                if (v) localStorage.setItem(localeStorageKey, v);
            } catch (_) {}
        },
        { immediate: true }
    );
    watch(
        currency,
        (v) => {
            try {
                if (v) localStorage.setItem(currencyStorageKey, v);
            } catch (_) {}
        },
        { immediate: true }
    );

    function setLocale(v) {
        if (SUPPORTED_LOCALES.includes(v)) locale.value = v;
    }

    function setCurrency(v) {
        const codes = currencies.map((c) => c.code);
        if (codes.includes(v)) currency.value = v;
    }

    function t(key) {
        const loc = locale.value || 'pt_BR';
        const byLocale = translations[loc] || translations.pt_BR || {};
        return byLocale[key] != null ? byLocale[key] : key;
    }

    const currencyList = computed(() => (Array.isArray(currencies) ? currencies : []));

    const currentCurrencyObj = computed(
        () => currencyList.value.find((c) => c.code === currency.value) || currencyList.value[0] || { code: 'BRL', symbol: 'R$', label: 'Real', rate_to_brl: 1 }
    );

    /** Converte preço em BRL para a moeda selecionada (price_brl * rate_to_brl). */
    function priceInCurrency(priceBrl) {
        const n = Number(priceBrl);
        if (Number.isNaN(n)) return 0;
        const obj = currentCurrencyObj.value;
        const rate = Number(obj.rate_to_brl) || 1;
        return Math.round(n * rate * 100) / 100;
    }

    function formatPrice(value, currencyCode) {
        const code = currencyCode || currency.value || 'BRL';
        const localeForFormat = code === 'BRL' ? 'pt-BR' : code === 'EUR' ? 'de-DE' : 'en-US';
        return new Intl.NumberFormat(localeForFormat, {
            style: 'currency',
            currency: code,
        }).format(value);
    }

    return {
        locale,
        setLocale,
        currency,
        setCurrency,
        t,
        currencies: currencyList,
        currentCurrencyObj,
        priceInCurrency,
        formatPrice,
        supportedLocales: SUPPORTED_LOCALES,
    };
}
