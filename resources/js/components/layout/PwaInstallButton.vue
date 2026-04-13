<script setup>
import { ref, computed, onMounted } from 'vue';
import { Smartphone } from 'lucide-vue-next';
import { usePwaInstall } from '@/composables/usePwaInstall';

const {
    canShowInstallButton,
    installPromptEvent,
    isIos,
    isMobile,
    isSecureContextForPwa,
    canTriggerNativeInstallPrompt,
    triggerInstall,
    registerListener,
    syncInstallPromptFromWindow,
} = usePwaInstall('painel');

const showFallbackMessage = ref(false);
let fallbackTimer = null;

const isAndroid = computed(() => isMobile.value && !isIos.value);
const showHttpsWarning = computed(() => showFallbackMessage.value && isAndroid.value && !isSecureContextForPwa.value);

async function handleInstallClick() {
    showFallbackMessage.value = false;
    if (fallbackTimer) {
        clearTimeout(fallbackTimer);
        fallbackTimer = null;
    }
    syncInstallPromptFromWindow();
    if (canTriggerNativeInstallPrompt.value) {
        await triggerInstall();
        return;
    }
    if (isIos.value) {
        triggerInstall();
        return;
    }
    showFallbackMessage.value = true;
    fallbackTimer = setTimeout(() => {
        showFallbackMessage.value = false;
        fallbackTimer = null;
    }, 8000);
}

onMounted(() => {
    registerListener();
});
</script>

<template>
    <div v-if="canShowInstallButton" class="space-y-1">
        <button
            type="button"
            class="menu-item group w-full justify-start menu-item-inactive"
            @click="handleInstallClick"
        >
            <span class="shrink-0 menu-item-icon-inactive">
                <Smartphone class="h-5 w-5" aria-hidden="true" />
            </span>
            <span class="truncate">Instalar App</span>
        </button>
        <!-- Android (ou outro mobile) sem prompt nativo: instruções para instalar pelo menu -->
        <p
            v-if="showHttpsWarning"
            class="px-4 py-2 text-xs text-zinc-500 dark:text-zinc-400"
        >
            Para instalar como app no Android, abra em <strong>HTTPS</strong> (cadeado na barra). Em HTTP, o Chrome cria apenas atalho e não exibe o prompt nativo de instalação.
        </p>
        <p
            v-else-if="showFallbackMessage && isAndroid"
            class="px-4 py-2 text-xs text-zinc-500 dark:text-zinc-400"
        >
            No Chrome, toque no menu (⋮) e escolha <strong>Instalar app</strong>. Se aparecer apenas <strong>Adicionar à tela inicial</strong>, o navegador ainda não considerou a página instalável.
        </p>
        <!-- Fallback genérico (outros casos) -->
        <p
            v-else-if="showFallbackMessage"
            class="px-4 py-2 text-xs text-zinc-500 dark:text-zinc-400"
        >
            Use o menu (⋮) do navegador e escolha <strong>Instalar app</strong> ou <strong>Adicionar à tela inicial</strong> para colocar o app na sua tela inicial.
        </p>
    </div>
</template>
