<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { X, Smartphone, Share } from 'lucide-vue-next';
import { usePwaInstall } from '@/composables/usePwaInstall';

const props = defineProps({
    appName: { type: String, default: 'App' },
    slug: { type: String, required: true },
});

const {
    installPromptEvent,
    showIosInstructions,
    isStandalone,
    isIos,
    tryGetDismissed,
    dismiss,
    triggerInstall,
    registerListener,
    unregisterListener,
} = usePwaInstall(props.slug);

const showBanner = ref(false);

// Quando o evento for capturado (Android), mostrar o banner se o usuário não dispensou
watch(
    installPromptEvent,
    (e) => {
        if (e && !isStandalone.value && !tryGetDismissed()) showBanner.value = true;
    },
    { immediate: true }
);

function install() {
    triggerInstall().then(() => {
        showBanner.value = false;
    });
}

onMounted(() => {
    if (isStandalone.value) return;
    registerListener();
    if (isIos.value && !tryGetDismissed() && !isStandalone.value) {
        showIosInstructions.value = true;
    }
});

onUnmounted(() => {
    unregisterListener();
});
</script>

<template>
    <!-- Banner Android/Chrome: Instalar app -->
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
            class="fixed bottom-0 left-0 right-0 z-50 border-t border-zinc-200 bg-white p-4 shadow-2xl dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div class="mx-auto flex max-w-md items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500/20 text-sky-500">
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
                        class="rounded-xl bg-sky-500 px-4 py-2.5 font-medium text-white shadow-lg transition hover:bg-sky-600 active:scale-[0.98]"
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

    <!-- Card iOS: Instruções para adicionar à tela inicial -->
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
            class="fixed bottom-4 left-4 right-4 z-50 max-w-sm rounded-2xl border border-zinc-200 bg-white p-5 shadow-2xl dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-500">
                    <Share class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-zinc-900 dark:text-zinc-100">Adicionar à tela inicial</p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        No Safari, toque no ícone <strong>Compartilhar</strong> (quadrado com seta para cima) na barra inferior. Em seguida, toque em <strong>« Adicionar à Tela de Início »</strong>.
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl bg-emerald-500 px-4 py-2.5 font-medium text-white transition hover:bg-emerald-600"
                        @click="dismiss"
                    >
                        Entendi
                    </button>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                    aria-label="Fechar"
                    @click="dismiss"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>
        </div>
    </Transition>
</template>
