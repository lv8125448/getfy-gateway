import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useI18n() {
    const page = usePage();
    const locale = computed(() => page.props?.i18n?.locale || 'pt_BR');
    const messages = computed(() => page.props?.i18n?.messages || {});
    const availableLanguages = computed(() => page.props?.i18n?.available_languages || []);

    function t(key, fallback = '') {
        const value = messages.value?.[key];
        if (typeof value === 'string' && value.trim() !== '') return value;
        if (fallback) return fallback;
        return key;
    }

    return {
        t,
        locale,
        messages,
        availableLanguages,
    };
}
