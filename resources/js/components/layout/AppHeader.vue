<script setup>
import { computed, inject, ref } from 'vue';
import { PanelsTopLeft, Bell, Globe2 } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { useSidebar } from '@/composables/useSidebar';
import ConquistasWidget from '@/components/layout/ConquistasWidget.vue';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import UserMenu from '@/components/layout/UserMenu.vue';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const isDashboard = computed(() => page.url === '/dashboard' || page.url.startsWith('/dashboard?'));
const showLanguage = computed(() => !!page.props?.auth?.user && !page.url.startsWith('/plataforma'));
const languageOpen = ref(false);

defineProps({
    pageTitle: { type: String, default: null },
    pageTitleBadge: { type: String, default: null },
});

const { toggleSidebar, isMobileOpen, isMobile } = useSidebar();

const openNotificationsPanel = inject('openNotificationsPanel', () => {});
const notificationsUnreadCount = inject('notificationsUnreadCount', { value: 0 });
const unreadBadge = computed(() => Math.max(0, notificationsUnreadCount?.value ?? 0));
const switchingLanguage = ref(false);
const { t, locale, availableLanguages } = useI18n();
const currentLanguageName = computed(() => {
    const current = (availableLanguages.value || []).find((lang) => lang.code === locale.value);
    return current?.name || t('header.language', 'Idioma');
});

async function switchLanguage(nextLocale) {
    if (!nextLocale || switchingLanguage.value || nextLocale === locale.value) return;
    switchingLanguage.value = true;
    try {
        await window.axios.post('/painel/idioma', { locale: nextLocale });
        languageOpen.value = false;
        router.reload({ preserveScroll: true });
    } finally {
        switchingLanguage.value = false;
    }
}
</script>

<template>
    <header class="z-[99998] flex shrink-0 w-full items-center justify-between gap-4 bg-transparent px-4 py-3 lg:px-6 lg:py-4">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button
                v-if="isMobile && !isMobileOpen"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                aria-label="Abrir menu"
                @click="toggleSidebar"
            >
                <PanelsTopLeft class="h-5 w-5" aria-hidden="true" />
            </button>
            <template v-if="pageTitle">
                <h1 class="truncate text-xl font-semibold text-zinc-900 dark:text-white md:text-2xl">
                    {{ pageTitle }}
                </h1>
                <span
                    v-if="pageTitleBadge"
                    class="shrink-0 truncate max-w-[160px] md:max-w-[220px] rounded-md bg-[var(--color-primary)]/15 px-2.5 py-0.5 text-xs font-medium text-[var(--color-primary)] dark:bg-[var(--color-primary)]/25 dark:text-[var(--color-primary)]"
                    :title="pageTitleBadge"
                >
                    {{ pageTitleBadge }}
                </span>
            </template>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <ConquistasWidget v-if="!isDashboard || !isMobile" />
            <div v-if="showLanguage" class="relative">
                <button
                    type="button"
                    class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    :aria-label="t('header.language', 'Idioma')"
                    :title="currentLanguageName"
                    @click="languageOpen = !languageOpen"
                >
                    <Globe2 class="h-5 w-5" aria-hidden="true" />
                </button>
                <div
                    v-if="languageOpen"
                    class="absolute right-0 z-[100000] mt-2 min-w-[180px] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <button
                        v-for="lang in availableLanguages"
                        :key="lang.code"
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        @click="switchLanguage(lang.code)"
                    >
                        <span>{{ lang.name }}</span>
                        <span v-if="lang.code === locale" class="text-xs text-[var(--color-primary)]">✓</span>
                    </button>
                </div>
            </div>
            <ThemeToggler />
            <button
                type="button"
                class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                :aria-label="t('header.notifications', 'Notificações')"
                @click="openNotificationsPanel()"
            >
                <Bell class="h-5 w-5" aria-hidden="true" />
                <span
                    v-if="unreadBadge > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-[var(--color-primary)] px-1 text-[10px] font-semibold text-white"
                >
                    {{ unreadBadge > 99 ? '99+' : unreadBadge }}
                </span>
            </button>
            <UserMenu />
        </div>
    </header>
</template>
