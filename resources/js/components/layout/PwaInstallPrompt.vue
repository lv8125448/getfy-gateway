<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { X, Smartphone, Share, Bell } from 'lucide-vue-next';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { usePanelPushSubscribe } from '@/composables/usePanelPushSubscribe';
import { usePage } from '@inertiajs/vue3';

const slug = 'painel';
const {
    installPromptEvent,
    showIosInstructions,
    showNotificationPromptAfterInstall,
    isStandalone,
    isIos,
    isMobile,
    tryGetDismissed,
    dismiss,
    triggerInstall,
    registerListener,
    unregisterListener,
    syncInstallPromptFromWindow,
    openIosInstructions,
} = usePwaInstall(slug);

const { registerAndSubscribe, pushRegistered, lastPushError } = usePanelPushSubscribe();
const page = usePage();
const appName = computed(() => page.props.appSettings?.app_name || 'Getfy');
const pushEnabled = computed(() => !!page.props.push_enabled);

const showBanner = ref(false);
const notificationPromptLoading = ref(false);

const NOTIFICATION_PROMPT_STORAGE_KEY = 'panel_notification_prompt_dismissed';
const NOTIFICATION_PROMPT_COOLDOWN_MS = 7 * 24 * 60 * 60 * 1000; // 7 dias

function wasNotificationPromptDismissedRecently() {
    try {
        const raw = localStorage.getItem(NOTIFICATION_PROMPT_STORAGE_KEY);
        if (!raw) return false;
        const ts = parseInt(raw, 10);
        return Date.now() - ts < NOTIFICATION_PROMPT_COOLDOWN_MS;
    } catch {
        return false;
    }
}

function shouldShowNotificationPromptInStandalone() {
    if (typeof window === 'undefined' || typeof Notification === 'undefined') return false;
    return (
        isStandalone.value &&
        pushEnabled.value &&
        Notification.permission === 'default' &&
        !pushRegistered.value &&
        !wasNotificationPromptDismissedRecently()
    );
}

watch(
    installPromptEvent,
    (e) => {
        if (e && !isStandalone.value && !tryGetDismissed()) {
            showBanner.value = true;
        }
    },
    { immediate: true }
);

async function install() {
    await triggerInstall();
    showBanner.value = false;
}

function closeNotificationPrompt(dismissed = false) {
    if (dismissed) {
        try {
            localStorage.setItem(NOTIFICATION_PROMPT_STORAGE_KEY, Date.now().toString());
        } catch {}
    }
    showNotificationPromptAfterInstall.value = false;
}

async function allowNotifications() {
    if (typeof Notification === 'undefined' || !pushEnabled.value) {
        closeNotificationPrompt();
        return;
    }
    notificationPromptLoading.value = true;
    try {
        const result = await Notification.requestPermission();
        if (result === 'granted') {
            await registerAndSubscribe();
        }
    } catch (_) {}
    notificationPromptLoading.value = false;
    closeNotificationPrompt(false);
}

let iosPromptTimer = null;
let notificationPromptTimer = null;
onMounted(() => {
    if (isStandalone.value) {
        // Atrasar um pouco para o layout estabilizar e pushRegistered (se outro componente chamar) ter valor estável
        notificationPromptTimer = setTimeout(() => {
            if (shouldShowNotificationPromptInStandalone()) {
                showNotificationPromptAfterInstall.value = true;
            }
        }, 400);
        return;
    }
    registerListener();
    syncInstallPromptFromWindow();
    if (installPromptEvent.value && !tryGetDismissed()) {
        showBanner.value = true;
    }
    // iOS: não tem beforeinstallprompt; exibir card "Adicionar à tela inicial" após breve delay
    if (isIos.value && !isStandalone.value && !tryGetDismissed()) {
        iosPromptTimer = setTimeout(() => {
            if (!isStandalone.value && !tryGetDismissed()) {
                openIosInstructions();
            }
        }, 600);
    }
});

onUnmounted(() => {
    if (iosPromptTimer) clearTimeout(iosPromptTimer);
    if (notificationPromptTimer) clearTimeout(notificationPromptTimer);
    unregisterListener();
});
</script>

<template>
    <!-- Banner Android: prompt de instalação fixo no mobile -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="showBanner && installPromptEvent && !isStandalone"
            class="fixed bottom-0 left-0 right-0 z-[99999] border-t border-zinc-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div class="mx-auto flex max-w-md items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                        <Smartphone class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">Instalar {{ appName }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Acesso rápido pela tela inicial</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-zinc-900 px-4 py-2.5 font-medium text-white shadow-lg transition hover:bg-zinc-800 active:scale-[0.98] dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100"
                        @click="install"
                    >
                        Instalar
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="dismiss(); showBanner = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Modal iOS: instruções para adicionar à tela inicial -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="showIosInstructions && isIos && !isStandalone"
            class="fixed inset-0 z-[99999] flex items-end justify-center p-4 pb-[max(2rem,calc(env(safe-area-inset-bottom)+1rem))] sm:items-center sm:p-6"
        >
            <!-- Backdrop -->
            <div
                class="absolute inset-0 bg-black/50"
                aria-hidden="true"
                @click="dismiss"
            />
            <!-- Card -->
            <div
                class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">
                        <Share class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Adicionar à tela inicial
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                            No Safari, toque no ícone <strong class="text-zinc-800 dark:text-zinc-200">Compartilhar</strong>
                            (quadrado com seta para cima) na barra inferior.
                        </p>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                            Em seguida, toque em <strong class="text-zinc-800 dark:text-zinc-200">« Adicionar à Tela de Início »</strong>.
                        </p>
                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl bg-zinc-900 px-4 py-3 font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100"
                            @click="dismiss"
                        >
                            Entendi
                        </button>
                    </div>
                    <button
                        type="button"
                        class="absolute right-4 top-4 rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="dismiss"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Modal: permitir notificações após instalar o PWA -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="showNotificationPromptAfterInstall && pushEnabled && typeof Notification !== 'undefined'"
            class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-black/50"
                aria-hidden="true"
                @click="closeNotificationPrompt(false)"
            />
            <div
                class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                        <Bell class="h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Permitir notificações?
                        </h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                            Receba avisos de novas vendas, PIX gerados e outras atualizações no {{ appName }}.
                        </p>
                        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="rounded-xl border border-zinc-300 bg-white px-4 py-3 font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                :disabled="notificationPromptLoading"
                                @click="closeNotificationPrompt(true)"
                            >
                                Agora não
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-[var(--color-primary)] px-4 py-3 font-medium text-white transition hover:opacity-90 disabled:opacity-70"
                                :disabled="notificationPromptLoading"
                                @click="allowNotifications"
                            >
                                {{ notificationPromptLoading ? 'Aguarde...' : 'Permitir' }}
                            </button>
                        </div>
                        <p
                            v-if="lastPushError && Notification.permission === 'granted' && !pushRegistered"
                            class="mt-3 text-xs text-zinc-500 dark:text-zinc-400"
                        >
                            Não foi possível concluir a ativação agora. Abra o painel de notificações e tente novamente.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="absolute right-4 top-4 rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="closeNotificationPrompt(true)"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
