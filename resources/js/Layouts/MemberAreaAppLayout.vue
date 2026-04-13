<script setup>
import { computed, ref, onMounted, watch, onUnmounted } from 'vue';
import { Link, usePage, Head, router } from '@inertiajs/vue3';
import PwaInstallPrompt from '@/components/member-area/PwaInstallPrompt.vue';
import MemberAreaNotificationsPanel from '@/components/member-area/MemberAreaNotificationsPanel.vue';
import Button from '@/components/ui/Button.vue';
import { Bell, ChevronDown, User, X, Camera, Lock, CheckCircle, AlertCircle, Menu, Trophy } from 'lucide-vue-next';

const page = usePage();
const props = computed(() => page.props);
const product = computed(() => props.value?.product ?? {});
const config = computed(() => props.value?.config ?? {});
const slug = computed(() => props.value?.slug ?? '');
const push_enabled = computed(() => props.value?.push_enabled ?? false);
const vapid_public = computed(() => props.value?.vapid_public ?? null);
const base_url = computed(() => props.value?.base_url ?? '');

const user = computed(() => props.value?.auth?.user ?? null);
const theme = computed(() => config.value?.theme ?? {});
const sidebar = computed(() => config.value?.sidebar ?? {});
const headerLogo = computed(() => config.value?.header?.logo_url ?? null);
const sidebarItems = computed(() => sidebar.value?.items ?? [
    { title: 'Início', icon: 'home', link: '/', open_external: false },
]);

/** Número de itens no nav: sidebar + comunidade (se ativa). Se > 2, em mobile mostra hamburger. */
const totalNavCount = computed(() => {
    const items = sidebarItems.value.length;
    const withCommunity = config.value?.community_enabled ? 1 : 0;
    return items + withCommunity;
});
const certificateEnabled = computed(() => (config.value?.certificate ?? {})?.enabled ?? false);
const showMobileHamburger = computed(() => totalNavCount.value > 2);

const gamificationEnabled = computed(() => (config.value?.gamification ?? {})?.enabled ?? false);
const gamificationAchievements = computed(() => props.value?.gamification_achievements ?? []);
const showGamificationBadge = computed(() => gamificationEnabled.value && gamificationAchievements.value.length > 0);
const gamificationDropdownOpen = ref(false);
const gamificationDropdownRef = ref(null);
const lastUnlockedAchievement = computed(() => {
    const list = gamificationAchievements.value.filter((a) => a.unlocked);
    return list.length > 0 ? list[list.length - 1] : null;
});

const newlyUnlockedQueue = ref([]);
const achievementModalOpen = ref(false);
const currentAchievementModal = computed(() => newlyUnlockedQueue.value[0] ?? null);
function closeAchievementModal() {
    if (newlyUnlockedQueue.value.length > 0) {
        newlyUnlockedQueue.value = newlyUnlockedQueue.value.slice(1);
    }
    achievementModalOpen.value = newlyUnlockedQueue.value.length > 0;
}
function openAchievementModal(achievement) {
    newlyUnlockedQueue.value = [achievement];
    achievementModalOpen.value = true;
}
function openAchievementModals(achievements) {
    if (achievements?.length) {
        newlyUnlockedQueue.value = [...achievements];
        achievementModalOpen.value = true;
    }
}

function handleClickOutsideGamificationDropdown(e) {
    if (gamificationDropdownRef.value && !gamificationDropdownRef.value.contains(e.target)) gamificationDropdownOpen.value = false;
}

const mobileMenuOpen = ref(false);
const headerScrolled = ref(false);

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

function onWindowScroll() {
    headerScrolled.value = typeof window !== 'undefined' && window.scrollY > 8;
}

const basePath = computed(() => `/m/${slug.value}`);
/** Path do login da área atual (slug em path ou /login em domínio/subdomínio próprio). */
const memberAreaLoginPath = computed(() => {
    if (typeof window !== 'undefined') {
        return window.location.pathname.startsWith('/m/') ? `${basePath.value}/login` : '/login';
    }
    const bu = props.value?.base_url;
    if (bu && typeof bu === 'string' && bu.includes('/m/')) {
        return `${basePath.value}/login`;
    }
    return '/login';
});
const logoutHref = computed(() => {
    const target = memberAreaLoginPath.value;
    return `/logout?redirect=${encodeURIComponent(target)}`;
});
const baseUrl = computed(() => {
    if (props.value?.base_url) return props.value.base_url;
    if (typeof window === 'undefined') return '';
    // Em host próprio/subdomínio, usar a origem atual para evitar montar /m/{slug} incorretamente.
    if (!window.location.pathname.startsWith('/m/')) return window.location.origin;
    return `${window.location.origin}${basePath.value}`;
});
const accountBaseUrl = computed(() => String(baseUrl.value || '').replace(/\/$/, ''));
/** Base path para API de notificações: /m/slug quando acesso por path, vazio quando por host. */
const notificationsApiBasePath = computed(() => {
    if (typeof window === 'undefined') return basePath.value;
    return window.location.pathname.startsWith('/m/') ? basePath.value : '';
});

const initials = computed(() => {
    if (!user.value?.name) return '?';
    const parts = String(user.value.name).trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return (parts[0][0] || '?').toUpperCase();
});

const notificationsPanelOpen = ref(false);
const memberNotificationsUnreadCount = ref(props.value?.member_notifications_unread_count ?? 0);
const accountMenuOpen = ref(false);
const accountMenuRef = ref(null);
const accountModalOpen = ref(false);

const profileName = ref('');
const profileAvatarFile = ref(null);
const profileAvatarPreview = ref(null);
const profileSaving = ref(false);
const profileError = ref('');
const profileSuccess = ref('');

const passwordCurrent = ref('');
const passwordNew = ref('');
const passwordConfirm = ref('');
const passwordSaving = ref(false);
const passwordError = ref('');
const passwordSuccess = ref('');

function openAccountModal() {
    accountMenuOpen.value = false;
    profileName.value = user.value?.name ?? '';
    profileAvatarFile.value = null;
    profileAvatarPreview.value = null;
    profileError.value = '';
    profileSuccess.value = '';
    passwordCurrent.value = '';
    passwordNew.value = '';
    passwordConfirm.value = '';
    passwordError.value = '';
    passwordSuccess.value = '';
    accountModalOpen.value = true;
}

function closeAccountModal() {
    accountModalOpen.value = false;
}

function onProfileAvatarChange(e) {
    const file = e.target?.files?.[0];
    if (!file) return;
    profileAvatarFile.value = file;
    profileAvatarPreview.value = URL.createObjectURL(file);
}

function handleClickOutsideAccountMenu(e) {
    if (accountMenuRef.value && !accountMenuRef.value.contains(e.target)) accountMenuOpen.value = false;
}

async function saveProfile() {
    if (!user.value || profileSaving.value) return;
    const name = profileName.value?.trim();
    if (!name) {
        profileError.value = 'Informe o nome.';
        return;
    }
    profileError.value = '';
    profileSuccess.value = '';
    profileSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const url = `${accountBaseUrl.value.replace(/\/$/, '')}/conta`;
    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('name', name);
    if (profileAvatarFile.value) formData.append('avatar', profileAvatarFile.value);
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            profileSuccess.value = data?.message ?? 'Perfil atualizado.';
            router.reload();
        } else {
            profileError.value = data?.message ?? data?.errors?.name?.[0] ?? data?.errors?.avatar?.[0] ?? 'Erro ao atualizar perfil.';
        }
    } catch (_) {
        profileError.value = 'Erro ao atualizar perfil.';
    } finally {
        profileSaving.value = false;
    }
}

async function savePassword() {
    if (!user.value || passwordSaving.value) return;
    if (!passwordCurrent.value || !passwordNew.value || !passwordConfirm.value) {
        passwordError.value = 'Preencha todos os campos.';
        return;
    }
    if (passwordNew.value !== passwordConfirm.value) {
        passwordError.value = 'A nova senha e a confirmação não conferem.';
        return;
    }
    if (passwordNew.value.length < 8) {
        passwordError.value = 'A nova senha deve ter no mínimo 8 caracteres.';
        return;
    }
    passwordError.value = '';
    passwordSuccess.value = '';
    passwordSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const url = `${accountBaseUrl.value.replace(/\/$/, '')}/conta/senha`;
    try {
        const res = await fetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                current_password: passwordCurrent.value,
                password: passwordNew.value,
                password_confirmation: passwordConfirm.value,
            }),
        });
        if (res.ok) {
            passwordSuccess.value = 'Senha alterada.';
            passwordCurrent.value = '';
            passwordNew.value = '';
            passwordConfirm.value = '';
        } else {
            const data = await res.json().catch(() => ({}));
            passwordError.value = data?.errors?.current_password?.[0] ?? data?.errors?.password?.[0] ?? data?.message ?? 'Erro ao alterar senha.';
        }
    } catch (_) {
        passwordError.value = 'Erro ao alterar senha.';
    } finally {
        passwordSaving.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutsideAccountMenu);
    document.addEventListener('click', handleClickOutsideGamificationDropdown);
    onWindowScroll();
    window.addEventListener('scroll', onWindowScroll, { passive: true });
});
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutsideAccountMenu);
    document.removeEventListener('click', handleClickOutsideGamificationDropdown);
    window.removeEventListener('scroll', onWindowScroll);
});

const faviconHref = computed(() => {
    const url = config.value?.logos?.favicon ?? config.value?.pwa?.favicon ?? null;
    if (!url || typeof window === 'undefined') return null;
    if (url.startsWith('http')) return url;
    return url.startsWith('/') ? `${window.location.origin}${url}` : `${window.location.origin}/${url.replace(/^\//, '')}`;
});

const manifestUrl = computed(() => {
    const base = baseUrl.value;
    if (!base || typeof window === 'undefined') return null;
    return `${base.endsWith('/') ? base.slice(0, -1) : base}/manifest.json`;
});

const themeColor = computed(() => config.value?.pwa?.theme_color || '#0ea5e9');
const appName = computed(() => config.value?.pwa?.name || product.value?.name || 'App');
const pageTitle = computed(() => product.value?.name || config.value?.pwa?.name || 'Área de Membros');

const canRegisterPush = computed(() => Boolean(
    push_enabled.value &&
    vapid_public.value &&
    typeof window !== 'undefined' &&
    typeof navigator !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window
));

/** Detecta se o app está rodando como PWA instalado (standalone). */
const isStandalonePwa = computed(() => {
    if (typeof window === 'undefined') return false;
    if (window.matchMedia('(display-mode: standalone)').matches) return true;
    if (window.matchMedia('(display-mode: fullscreen)').matches && window.navigator.standalone === false) return false;
    return !!window.navigator.standalone;
});

const pushSubscribing = ref(false);
const pushRegistered = ref(false);
const pushAutoPromptAttempted = ref(false);

/** No PWA instalado usa chave separada para "dispensado", assim o prompt aparece após instalar e logar mesmo se dispensou no browser. */
const PUSH_PROMPT_DISMISSED_KEY = computed(() => `push_prompt_dismissed_${slug.value || 'default'}${isStandalonePwa.value ? '_standalone' : ''}`);

function shouldAutoPromptPush() {
    if (!canRegisterPush.value || pushRegistered.value || pushSubscribing.value || pushAutoPromptAttempted.value) return false;
    if (typeof Notification === 'undefined') return false;
    if (Notification.permission === 'denied') return false;
    try {
        const dismissed = localStorage.getItem(PUSH_PROMPT_DISMISSED_KEY.value);
        if (dismissed) {
            const age = Date.now() - parseInt(dismissed, 10);
            if (age < 24 * 60 * 60 * 1000) return false; // não insistir por 24h se dispensou
        }
    } catch (_) {}
    return true;
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}

function serializeSubscription(sub) {
    const p256dh = sub?.getKey?.('p256dh');
    const auth = sub?.getKey?.('auth');
    return {
        endpoint: sub?.endpoint,
        keys: {
            p256dh: p256dh ? btoa(String.fromCharCode.apply(null, new Uint8Array(p256dh))) : '',
            auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : '',
        },
    };
}

async function syncMemberPushSubscription(sub, subscribeUrl, csrf) {
    const body = serializeSubscription(sub);
    if (!body.endpoint || !body.keys?.p256dh || !body.keys?.auth) {
        throw new Error('Subscription inválida para sincronização.');
    }
    const res = await fetch(subscribeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data?.success) {
        throw new Error(data?.message || 'Não foi possível sincronizar a inscrição de notificações.');
    }
    return true;
}

/** Verifica se já existe subscription no browser e atualiza pushRegistered (para o painel de notificações). */
async function checkExistingSubscriptionForPanel() {
    if (!canRegisterPush.value || typeof navigator === 'undefined' || !navigator.serviceWorker?.getRegistration) return;
    const scope = baseUrl.value ? (baseUrl.value.endsWith('/') ? baseUrl.value : baseUrl.value + '/') : null;
    if (!scope) return;
    try {
        const reg = await navigator.serviceWorker.getRegistration(scope);
        const existing = await reg?.pushManager?.getSubscription?.();
        if (!existing) return;
        const subscribeUrl = `${scope}push-subscribe`;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        await syncMemberPushSubscription(existing, subscribeUrl, csrf);
        pushRegistered.value = true;
    } catch (e) {
        console.warn('MemberArea push sync failed (panel check):', e);
    }
}

async function registerPushSubscription() {
    if (!canRegisterPush.value || pushSubscribing.value) return false;
    const scope = baseUrl.value.endsWith('/') ? baseUrl.value : baseUrl.value + '/';
    const swUrl = `${scope}sw.js`;
    const subscribeUrl = `${scope}push-subscribe`;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    pushSubscribing.value = true;
    pushAutoPromptAttempted.value = true;
    try {
        const reg = await navigator.serviceWorker.register(swUrl, { scope });
        const existing = await reg.pushManager?.getSubscription?.();
        const sub = existing || await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid_public.value),
        });
        await syncMemberPushSubscription(sub, subscribeUrl, csrf);
        pushRegistered.value = true;
        return true;
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            try {
                localStorage.setItem(PUSH_PROMPT_DISMISSED_KEY.value, Date.now().toString());
            } catch (_) {}
            return false;
        }
        console.error('Push subscribe error', e);
        alert(e?.message || 'Não foi possível ativar as notificações. Verifique as permissões do navegador.');
        return false;
    } finally {
        pushSubscribing.value = false;
    }
}

onMounted(() => {
    if (pageTitle.value) document.title = pageTitle.value;
    const scope = baseUrl.value ? (baseUrl.value.endsWith('/') ? baseUrl.value : baseUrl.value + '/') : null;
    // No PWA instalado (standalone), delay maior para o prompt aparecer depois de logar e ver a tela
    const promptDelayMs = isStandalonePwa.value ? 3000 : 1500;
    function schedulePushPrompt() {
        if (!shouldAutoPromptPush()) return;
        setTimeout(() => {
            if (shouldAutoPromptPush()) registerPushSubscription();
        }, promptDelayMs);
    }
    if (scope && typeof navigator !== 'undefined' && navigator.serviceWorker) {
        navigator.serviceWorker.register(`${scope}sw.js`, { scope }).then(async (reg) => {
            if (reg.pushManager && canRegisterPush.value) {
                try {
                    const existing = await reg.pushManager.getSubscription();
                    if (existing) {
                        const subscribeUrl = `${scope}push-subscribe`;
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                        await syncMemberPushSubscription(existing, subscribeUrl, csrf);
                        pushRegistered.value = true;
                        return;
                    }
                } catch (e) {
                    console.warn('MemberArea push sync failed (onMounted):', e);
                }
            }
            schedulePushPrompt();
        }).catch(() => {
            schedulePushPrompt();
        });
    }
});

watch(pageTitle, (t) => { if (t) document.title = t; }, { immediate: true });

watch(
    () => [props.value?.flash?.newly_unlocked_achievements, props.value?.newly_unlocked_achievements],
    ([flashList, pageList]) => {
        const list = flashList ?? pageList ?? [];
        if (Array.isArray(list) && list.length > 0) openAchievementModals(list);
    },
    { immediate: true }
);

watch(
    () => props.value?.member_notifications_unread_count,
    (v) => { if (v !== undefined) memberNotificationsUnreadCount.value = v; },
    { immediate: true }
);
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <link v-if="manifestUrl" rel="manifest" :href="manifestUrl" />
        <meta name="theme-color" :content="themeColor" />
        <meta name="mobile-web-app-capable" content="yes" />
        <link v-if="faviconHref" rel="icon" :href="faviconHref" />
    </Head>
    <div
        class="min-h-screen transition-colors"
        :style="{
            '--ma-primary': theme.primary || '#0ea5e9',
            '--ma-bg': theme.background || '#18181b',
            '--ma-sidebar-bg': theme.sidebar_bg || '#27272a',
            '--ma-text': theme.text || '#f8fafc',
        }"
    >
        <!-- Header: logo/nav à esquerda, conta e notificações à direita; overflow-visible para dropdowns não serem cortados -->
        <header
            class="fixed left-0 right-0 top-0 z-30 flex h-14 items-center justify-between gap-4 overflow-visible px-4 transition-[background] duration-300 print:hidden md:px-6"
            :class="[headerScrolled ? 'bg-black/30 backdrop-blur-md' : 'bg-transparent']"
            :style="{ color: 'var(--ma-text)' }"
        >
            <div class="flex min-w-0 shrink items-center gap-4 md:gap-6">
                <Link :href="basePath" class="flex shrink-0 items-center gap-4" @click="closeMobileMenu">
                    <img
                        v-if="headerLogo"
                        :src="headerLogo"
                        :alt="product?.name || 'Logo'"
                        class="h-8 w-auto max-w-[180px] object-contain object-left"
                    />
                    <span v-else class="text-lg font-semibold text-white drop-shadow-md">
                        {{ product?.name || 'Área de Membros' }}
                    </span>
                </Link>
                <!-- Nav: escondido em mobile quando há hamburger; visível em desktop ou quando <= 2 itens -->
                <nav
                    class="hidden items-center gap-1 md:flex"
                    :class="{ '!flex': showMobileHamburger === false }"
                >
                    <template v-for="item in sidebarItems" :key="item.title">
                        <a
                            v-if="item.open_external"
                            :href="item.link"
                            target="_blank"
                            rel="noopener"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-white/90 drop-shadow hover:bg-white/10"
                        >
                            {{ item.title }}
                        </a>
                        <Link
                            v-else
                            :href="item.link.startsWith('/') ? basePath + item.link : basePath + '/' + item.link"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-white/90 drop-shadow hover:bg-white/10"
                        >
                            {{ item.title }}
                        </Link>
                    </template>
                    <Link
                        v-if="config?.community_enabled"
                        :href="`${basePath}/comunidade`"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-white/90 drop-shadow hover:bg-white/10"
                    >
                        Comunidade
                    </Link>
                </nav>
                <!-- Botão hamburger: só quando mais de 2 itens E em telas pequenas (md:hidden quando showMobileHamburger) -->
                <button
                    v-if="showMobileHamburger"
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-lg text-white/90 hover:bg-white/10 md:hidden"
                    aria-label="Abrir menu"
                    @click="mobileMenuOpen = true"
                >
                    <Menu class="h-6 w-6" />
                </button>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <!-- Gamificação: badge + dropdown -->
                <div v-if="showGamificationBadge" ref="gamificationDropdownRef" class="relative">
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-white/90 drop-shadow transition hover:bg-white/10"
                        aria-label="Conquistas"
                        :aria-expanded="gamificationDropdownOpen"
                        @click.stop="gamificationDropdownOpen = !gamificationDropdownOpen"
                    >
                        <img
                            v-if="lastUnlockedAchievement?.image_url"
                            :src="lastUnlockedAchievement.image_url"
                            alt="Conquistas"
                            class="h-8 w-8 rounded-full object-cover"
                        />
                        <Trophy v-else class="h-6 w-6" />
                    </button>
                    <div
                        v-show="gamificationDropdownOpen"
                        class="absolute right-0 z-50 mt-2 w-72 max-h-[80vh] overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <div class="sticky top-0 border-b border-zinc-100 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Suas conquistas</h3>
                        </div>
                        <div class="p-2">
                            <div
                                v-for="ach in gamificationAchievements"
                                :key="ach.id"
                                class="flex items-center gap-3 rounded-lg p-2 transition"
                                :class="ach.unlocked ? '' : 'opacity-50'"
                            >
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full" :class="ach.unlocked ? '' : 'grayscale'">
                                    <img v-if="ach.image_url" :src="ach.image_url" :alt="ach.title" class="h-full w-full object-cover" />
                                    <div v-else class="flex h-full w-full items-center justify-center bg-zinc-200 dark:bg-zinc-700">
                                        <Trophy class="h-6 w-6 text-zinc-500" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ ach.title }}</p>
                                    <p v-if="ach.unlocked" class="text-xs text-zinc-500 dark:text-zinc-400">{{ ach.description }}</p>
                                    <p v-else-if="ach.requirement_text" class="text-xs text-amber-600 dark:text-amber-400">Para desbloquear: {{ ach.requirement_text }}</p>
                                </div>
                                <CheckCircle v-if="ach.unlocked" class="h-5 w-5 shrink-0 text-emerald-500" />
                            </div>
                        </div>
                    </div>
                </div>
                <button
                    v-if="canRegisterPush && !pushRegistered"
                    class="hidden rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/10 hover:text-white md:block"
                    :class="{ '!block': !showMobileHamburger }"
                    :disabled="pushSubscribing"
                    @click="registerPushSubscription"
                >
                    {{ pushSubscribing ? 'Ativando…' : 'Ativar notificações' }}
                </button>
                <button
                    v-if="user"
                    type="button"
                    class="relative flex h-9 w-9 items-center justify-center rounded-lg text-white/90 drop-shadow transition hover:bg-white/10"
                    aria-label="Notificações"
                    @click="notificationsPanelOpen = true"
                >
                    <Bell class="h-5 w-5" />
                    <span
                        v-if="memberNotificationsUnreadCount > 0"
                        class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full px-1 text-[10px] font-semibold text-white"
                        :style="{ backgroundColor: 'var(--ma-primary)' }"
                    >
                        {{ memberNotificationsUnreadCount > 99 ? '99+' : memberNotificationsUnreadCount }}
                    </span>
                </button>
                <div v-if="user" ref="accountMenuRef" class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-white/90 drop-shadow hover:bg-white/10"
                        :aria-expanded="accountMenuOpen"
                        aria-haspopup="true"
                        @click.stop="accountMenuOpen = !accountMenuOpen"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full text-xs font-medium"
                            :style="{ backgroundColor: 'var(--ma-primary)' }"
                        >
                            <img
                                v-if="user.avatar_url"
                                :src="user.avatar_url"
                                :alt="user.name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>{{ initials }}</span>
                        </span>
                        <span class="hidden max-w-[100px] truncate text-sm font-medium md:inline">{{ user.name }}</span>
                        <ChevronDown
                            class="h-4 w-4 shrink-0 transition-transform"
                            :class="{ 'rotate-180': accountMenuOpen }"
                        />
                    </button>
                    <div
                        v-show="accountMenuOpen"
                        class="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                        role="menu"
                    >
                        <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ user.name }}</p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ user.email }}</p>
                        </div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            role="menuitem"
                            @click="openAccountModal"
                        >
                            <User class="h-4 w-4" />
                            Minha conta
                        </button>
                        <Link
                            v-if="certificateEnabled"
                            :href="`${basePath}/certificado`"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            role="menuitem"
                            @click="accountMenuOpen = false"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                            Certificado
                        </Link>
                        <Link
                            :href="logoutHref"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            role="menuitem"
                            @click="accountMenuOpen = false"
                        >
                            Sair
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- Overlay + painel do menu mobile (hamburger) -->
        <Teleport to="body">
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-40 md:hidden"
                aria-hidden="true"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="closeMobileMenu"
                />
                <div
                    class="absolute right-0 top-0 bottom-0 flex h-full w-72 max-w-[85vw] flex-col border-l border-zinc-700 bg-zinc-900 shadow-2xl"
                    :style="{ paddingTop: '3.5rem' }"
                >
                    <button
                        type="button"
                        class="absolute right-3 top-3 rounded-lg p-2 text-zinc-400 hover:bg-zinc-800 hover:text-white"
                        aria-label="Fechar menu"
                        @click="closeMobileMenu"
                    >
                        <X class="h-5 w-5" />
                    </button>
                    <nav class="flex flex-col gap-1 px-4 py-2">
                        <template v-for="item in sidebarItems" :key="item.title">
                            <a
                                v-if="item.open_external"
                                :href="item.link"
                                target="_blank"
                                rel="noopener"
                                class="rounded-lg px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-800 hover:text-white"
                                @click="closeMobileMenu"
                            >
                                {{ item.title }}
                            </a>
                            <Link
                                v-else
                                :href="item.link.startsWith('/') ? basePath + item.link : basePath + '/' + item.link"
                                class="rounded-lg px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-800 hover:text-white"
                                @click="closeMobileMenu"
                            >
                                {{ item.title }}
                            </Link>
                        </template>
                        <Link
                            v-if="config?.community_enabled"
                            :href="`${basePath}/comunidade`"
                            class="rounded-lg px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-800 hover:text-white"
                            @click="closeMobileMenu"
                        >
                            Comunidade
                        </Link>
                    </nav>
                    <div v-if="canRegisterPush && !pushRegistered" class="border-t border-zinc-700 px-4 py-3">
                        <button
                            type="button"
                            class="w-full rounded-lg bg-zinc-800 px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-700"
                            :disabled="pushSubscribing"
                            @click="registerPushSubscription(); closeMobileMenu()"
                        >
                            {{ pushSubscribing ? 'Ativando…' : 'Ativar notificações' }}
                        </button>
                    </div>
                    <div v-if="user" class="mt-auto border-t border-zinc-700 px-4 py-4">
                        <div class="mb-3 flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-medium text-white"
                                :style="{ backgroundColor: 'var(--ma-primary)' }"
                            >
                                <img
                                    v-if="user.avatar_url"
                                    :src="user.avatar_url"
                                    :alt="user.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initials }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-zinc-100">{{ user.name }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ user.email }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="openAccountModal(); closeMobileMenu()"
                            >
                                <User class="h-4 w-4" />
                                Minha conta
                            </button>
                            <Link
                                v-if="certificateEnabled"
                                :href="`${basePath}/certificado`"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="closeMobileMenu"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                                Certificado
                            </Link>
                            <Link
                                :href="logoutHref"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="closeMobileMenu"
                            >
                                Sair
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <div class="min-h-screen pt-14 print:pt-0" :style="{ backgroundColor: 'var(--ma-bg)', color: 'var(--ma-text)' }">
            <main class="px-6 pb-6 print:p-0">
                <slot />
            </main>
        </div>
        <PwaInstallPrompt v-if="slug" :app-name="appName" :slug="slug" />
        <MemberAreaNotificationsPanel
            :open="notificationsPanelOpen"
            :base-path="notificationsApiBasePath"
            :push-enabled="push_enabled"
            :push-can-register="canRegisterPush"
            :push-registered="pushRegistered"
            :push-subscribing="pushSubscribing"
            :register-push="registerPushSubscription"
            :check-existing-subscription="checkExistingSubscriptionForPanel"
            @update:open="notificationsPanelOpen = $event"
            @unread-count-update="memberNotificationsUnreadCount = $event"
        />

        <!-- Modal Minha conta -->
        <Teleport to="body">
            <div
                v-if="accountModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="account-modal-title"
                @keydown.escape="closeAccountModal"
            >
                <div
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-2xl"
                    @click.stop
                >
                    <!-- Header com faixa de destaque -->
                    <div
                        class="relative flex items-center justify-between px-6 py-5"
                        :style="{ background: 'linear-gradient(135deg, var(--ma-primary) 0%, color-mix(in srgb, var(--ma-primary) 80%, black) 100%)' }"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/20 text-lg font-semibold text-white backdrop-blur"
                            >
                                <img
                                    v-if="user?.avatar_url && !profileAvatarPreview"
                                    :src="user.avatar_url"
                                    :alt="user.name"
                                    class="h-full w-full object-cover"
                                />
                                <User v-else-if="profileAvatarPreview" class="h-6 w-6 text-white" />
                                <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
                            </span>
                            <div>
                                <h2 id="account-modal-title" class="text-lg font-semibold text-white">Minha conta</h2>
                                <p class="text-sm text-white/80">Altere seu perfil e senha</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2.5 text-white/90 transition hover:bg-white/20 hover:text-white"
                            aria-label="Fechar"
                            @click="closeAccountModal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto p-6 space-y-6 bg-zinc-900">
                        <!-- Card Perfil -->
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800/50 p-5">
                            <div class="mb-4 flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :style="{ backgroundColor: 'var(--ma-primary)' }">
                                    <User class="h-4 w-4" />
                                </div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Perfil</h3>
                            </div>
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                <label class="group relative flex shrink-0 cursor-pointer">
                                    <span
                                        class="flex h-24 w-24 overflow-hidden rounded-2xl border-2 border-zinc-600 text-2xl font-medium shadow-inner transition group-hover:border-[var(--ma-primary)]"
                                        :style="{ backgroundColor: 'var(--ma-primary)', color: 'white' }"
                                    >
                                        <img
                                            v-if="profileAvatarPreview || user?.avatar_url"
                                            :src="profileAvatarPreview || user?.avatar_url"
                                            :alt="user?.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--ma-primary)] text-white shadow-lg transition group-hover:scale-105"
                                    >
                                        <Camera class="h-4 w-4" />
                                    </span>
                                    <input type="file" accept="image/*" class="sr-only" @change="onProfileAvatarChange" />
                                </label>
                                <div class="min-w-0 flex-1 space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nome</label>
                                        <input
                                            v-model="profileName"
                                            type="text"
                                            class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                            placeholder="Seu nome"
                                        />
                                    </div>
                                    <p class="text-xs text-zinc-500">Este nome aparecerá nos comentários e na comunidade.</p>
                                </div>
                            </div>
                            <div v-if="profileError" class="mt-4 flex items-center gap-2 rounded-xl bg-red-950/50 px-4 py-3 text-sm text-red-300">
                                <AlertCircle class="h-4 w-4 shrink-0" />
                                {{ profileError }}
                            </div>
                            <div v-if="profileSuccess" class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-950/50 px-4 py-3 text-sm text-emerald-300">
                                <CheckCircle class="h-4 w-4 shrink-0" />
                                {{ profileSuccess }}
                            </div>
                            <button
                                type="button"
                                class="mt-4 w-full rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95 disabled:opacity-50"
                                :style="{ backgroundColor: 'var(--ma-primary)' }"
                                :disabled="profileSaving"
                                @click="saveProfile"
                            >
                                {{ profileSaving ? 'Salvando…' : 'Salvar perfil' }}
                            </button>
                        </div>

                        <!-- Card Senha -->
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800/50 p-5">
                            <div class="mb-4 flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :style="{ backgroundColor: 'var(--ma-primary)' }">
                                    <Lock class="h-4 w-4" />
                                </div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Alterar senha</h3>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Senha atual</label>
                                    <input
                                        v-model="passwordCurrent"
                                        type="password"
                                        class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                        placeholder="••••••••"
                                        autocomplete="current-password"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nova senha</label>
                                    <input
                                        v-model="passwordNew"
                                        type="password"
                                        class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                        placeholder="Mínimo 8 caracteres"
                                        autocomplete="new-password"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Confirmar nova senha</label>
                                    <input
                                        v-model="passwordConfirm"
                                        type="password"
                                        class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                    />
                                </div>
                            </div>
                            <div v-if="passwordError" class="mt-4 flex items-center gap-2 rounded-xl bg-red-950/50 px-4 py-3 text-sm text-red-300">
                                <AlertCircle class="h-4 w-4 shrink-0" />
                                {{ passwordError }}
                            </div>
                            <div v-if="passwordSuccess" class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-950/50 px-4 py-3 text-sm text-emerald-300">
                                <CheckCircle class="h-4 w-4 shrink-0" />
                                {{ passwordSuccess }}
                            </div>
                            <button
                                type="button"
                                class="mt-4 w-full rounded-xl border-2 border-[var(--ma-primary)] bg-transparent px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--ma-primary)]/20 disabled:opacity-50"
                                :disabled="passwordSaving"
                                @click="savePassword"
                            >
                                {{ passwordSaving ? 'Alterando…' : 'Alterar senha' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: conquista desbloqueada -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="achievementModalOpen && currentAchievementModal"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                    aria-modal="true"
                    role="dialog"
                >
                    <div
                        class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                        @click="closeAchievementModal"
                    />
                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="currentAchievementModal"
                            class="relative max-w-sm w-full rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
                            @click.stop
                        >
                            <div class="flex flex-col items-center text-center">
                                <div class="mb-4 flex h-24 w-24 items-center justify-center overflow-hidden rounded-full ring-4 ring-[var(--ma-primary)]/30">
                                    <img
                                        v-if="currentAchievementModal.image_url"
                                        :src="currentAchievementModal.image_url"
                                        :alt="currentAchievementModal.title"
                                        class="h-full w-full object-cover"
                                    />
                                    <Trophy v-else class="h-12 w-12 text-[var(--ma-primary)]" />
                                </div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-[var(--ma-primary)]">Conquista desbloqueada</p>
                                <h3 class="mb-2 text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ currentAchievementModal.title }}</h3>
                                <p v-if="currentAchievementModal.description" class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">{{ currentAchievementModal.description }}</p>
                                <Button type="button" @click="closeAchievementModal">Continuar</Button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
