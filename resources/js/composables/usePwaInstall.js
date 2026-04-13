import { ref, computed } from 'vue';

// Estado compartilhado entre Login e PwaInstallPrompt (mesma página)
const installPromptEvent = ref(typeof window !== 'undefined' ? window.__pwaInstallPrompt ?? null : null);
const showIosInstructions = ref(false);
const showNotificationPromptAfterInstall = ref(false);
let listenerRegistered = false;

function handleBeforeInstallPrompt(e) {
    e.preventDefault();
    installPromptEvent.value = e;
    if (typeof window !== 'undefined') window.__pwaInstallPrompt = e;
}

function registerListenerOnce() {
    if (typeof window === 'undefined' || listenerRegistered) return;
    listenerRegistered = true;
    if (installPromptEvent.value === null && window.__pwaInstallPrompt) {
        installPromptEvent.value = window.__pwaInstallPrompt;
    }
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt, { capture: true });
}

export function usePwaInstall(slug = '') {
    // Registra o listener na primeira vez que o composable é usado (ex.: na página de login)
    registerListenerOnce();
    const STORAGE_KEY = `pwa_install_dismissed_${slug || 'default'}`;

    const isStandalone = computed(() => {
        if (typeof window === 'undefined') return false;
        return (
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true ||
            document.referrer.includes('android-app://')
        );
    });

    const isMobile = computed(() => {
        if (typeof navigator === 'undefined') return false;
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    });

    const isIos = computed(() => {
        if (typeof navigator === 'undefined') return false;
        return (
            /iPad|iPhone|iPod/.test(navigator.userAgent) ||
            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
        );
    });

    const canShowInstallButton = computed(
        () => isMobile.value && !isStandalone.value
    );

    const isSecureContextForPwa = computed(() => {
        if (typeof window === 'undefined') return false;
        return window.isSecureContext === true;
    });

    const canTriggerNativeInstallPrompt = computed(() => {
        return !!installPromptEvent.value && isSecureContextForPwa.value;
    });

    function tryGetDismissed() {
        try {
            const t = localStorage.getItem(STORAGE_KEY);
            if (!t) return false;
            const age = Date.now() - parseInt(t, 10);
            return age < 7 * 24 * 60 * 60 * 1000;
        } catch (_) {
            return false;
        }
    }

    function dismiss() {
        showIosInstructions.value = false;
        try {
            localStorage.setItem(STORAGE_KEY, Date.now().toString());
        } catch (_) {}
    }

    async function triggerInstall() {
        if (installPromptEvent.value) {
            // Abre o diálogo nativo de instalação na hora (Android/Chrome)
            installPromptEvent.value.prompt();
            try {
                const { outcome } = await installPromptEvent.value.userChoice;
                if (outcome === 'accepted') {
                    installPromptEvent.value = null;
                    showNotificationPromptAfterInstall.value = true;
                }
            } catch (_) {
                installPromptEvent.value = null;
            }
        } else if (isIos.value) {
            showIosInstructions.value = true;
        }
    }

    function handleBeforeInstallPromptInComponent(e) {
        e.preventDefault();
        installPromptEvent.value = e;
    }

    function registerListener() {
        registerListenerOnce();
    }

    function unregisterListener() {
        if (!listenerRegistered) return;
        listenerRegistered = false;
        window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt, { capture: true });
        installPromptEvent.value = null;
    }

    /** Sincroniza o ref com window.__pwaInstallPrompt (evento pode ter disparado antes da hidratação Vue). */
    function syncInstallPromptFromWindow() {
        if (typeof window !== 'undefined' && window.__pwaInstallPrompt && !installPromptEvent.value) {
            installPromptEvent.value = window.__pwaInstallPrompt;
        }
    }

    /** Abre o card de instruções iOS (Adicionar à tela inicial). */
    function openIosInstructions() {
        showIosInstructions.value = true;
    }

    return {
        installPromptEvent,
        showIosInstructions,
        showNotificationPromptAfterInstall,
        isStandalone,
        isMobile,
        isIos,
        isSecureContextForPwa,
        canTriggerNativeInstallPrompt,
        canShowInstallButton,
        tryGetDismissed,
        dismiss,
        triggerInstall,
        registerListener,
        unregisterListener,
        handleBeforeInstallPromptInComponent,
        syncInstallPromptFromWindow,
        openIosInstructions,
    };
}
