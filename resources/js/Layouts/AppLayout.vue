<script setup>
import { computed, ref, watch, watchEffect, provide, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useSidebarProvider } from '@/composables/useSidebar';
import { usePanelPushSubscribe } from '@/composables/usePanelPushSubscribe';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import AppHeader from '@/components/layout/AppHeader.vue';
import MobileBottomNav from '@/components/layout/MobileBottomNav.vue';
import PwaInstallPrompt from '@/components/layout/PwaInstallPrompt.vue';
import NotificationsPanel from '@/components/layout/NotificationsPanel.vue';
import Backdrop from '@/components/layout/Backdrop.vue';
import FlashToast from '@/components/layout/FlashToast.vue';
import CloudBillingBanner from '@/components/layout/CloudBillingBanner.vue';
import KycBanner from '@/components/layout/KycBanner.vue';

const { isExpanded } = useSidebarProvider();
usePanelPushSubscribe();
const page = usePage();
const pageTitle = computed(() => page.props.pageTitle ?? null);
const pageTitleBadge = computed(() => page.props.pageTitleBadge ?? null);
const contentMaxWidth = computed(() => (page.props.layoutFullWidth ? 'max-w-[1600px]' : 'max-w-7xl'));
const layoutContentFlushLeft = computed(() => !!page.props.layoutContentFlushLeft);
const isSellerDashboard = computed(() => page.url === '/dashboard' || page.url.startsWith('/dashboard?'));
const dashboardBanners = computed(() => (Array.isArray(page.props.dashboard_banners) ? page.props.dashboard_banners : []));
const dashboardCarouselIndex = ref(0);
let dashboardCarouselTimer = null;

const showNotificationsPanel = ref(false);
const notificationsUnreadCount = ref(page.props.notifications_unread_count ?? 0);
watch(
    () => page.props.notifications_unread_count,
    (v) => {
        notificationsUnreadCount.value = v ?? 0;
    }
);
provide('openNotificationsPanel', () => {
    showNotificationsPanel.value = true;
});
provide('notificationsUnreadCount', notificationsUnreadCount);

function onNotificationsUnreadCountUpdate(count) {
    notificationsUnreadCount.value = count;
}

watchEffect(() => {
    const primary = page.props.appSettings?.theme_primary || '#0ea5e9';
    document.documentElement.style.setProperty('--color-primary', primary);
});

const dashboardCurrentBanner = computed(() => {
    if (!dashboardBanners.value.length) return null;
    const idx = dashboardCarouselIndex.value % dashboardBanners.value.length;
    return dashboardBanners.value[idx];
});

function dashboardBannerUrl(item) {
    if (!item) return '';
    const isMobile = typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
    if (isMobile) return item.mobile_url || item.desktop_url || '';
    return item.desktop_url || item.mobile_url || '';
}

function stopDashboardCarousel() {
    if (dashboardCarouselTimer) {
        clearInterval(dashboardCarouselTimer);
        dashboardCarouselTimer = null;
    }
}

function startDashboardCarousel() {
    stopDashboardCarousel();
    if (!isSellerDashboard.value || dashboardBanners.value.length <= 1) return;
    dashboardCarouselTimer = setInterval(() => {
        dashboardCarouselIndex.value = (dashboardCarouselIndex.value + 1) % dashboardBanners.value.length;
    }, 5000);
}

watch([isSellerDashboard, dashboardBanners], () => {
    dashboardCarouselIndex.value = 0;
    startDashboardCarousel();
}, { immediate: true });

onBeforeUnmount(() => {
    stopDashboardCarousel();
});
</script>

<template>
    <div class="min-h-screen bg-zinc-100 dark:bg-zinc-900">
        <AppSidebar />
        <slot name="sidebar-after-nav" />
        <Backdrop />
        <div
            class="flex min-h-screen flex-col transition-all duration-300 ease-in-out p-3 md:p-4 lg:p-6"
            :class="[
                isExpanded ? 'lg:ml-[260px]' : 'lg:ml-[64px]',
            ]"
        >
            <div class="flex w-full shrink-0 flex-col gap-2">
                <div class="-mx-3 md:-mx-4 lg:-mx-6">
                    <CloudBillingBanner />
                    <KycBanner />
                </div>
                <AppHeader :page-title="pageTitle" :page-title-badge="pageTitleBadge" />
                <slot name="header-actions" />
            </div>
            <div
                v-if="isSellerDashboard && dashboardBanners.length"
                class="mb-4 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/40"
            >
                <div class="relative">
                    <img
                        v-if="dashboardCurrentBanner"
                        :key="dashboardCurrentBanner.id"
                        :src="dashboardBannerUrl(dashboardCurrentBanner)"
                        :alt="dashboardCurrentBanner.title || 'Banner da dashboard'"
                        class="h-[96px] w-full object-cover md:h-[112px] lg:h-[128px]"
                    />
                    <div v-if="dashboardBanners.length > 1" class="pointer-events-none absolute inset-x-0 bottom-3 flex items-center justify-center gap-2 px-4">
                        <button
                            v-for="(item, idx) in dashboardBanners"
                            :key="item.id"
                            type="button"
                            class="pointer-events-auto h-2.5 w-2.5 rounded-full transition"
                            :class="idx === dashboardCarouselIndex ? 'bg-[var(--color-primary)]' : 'bg-white/80'"
                            :aria-label="`Ir para banner ${idx + 1}`"
                            @click="dashboardCarouselIndex = idx"
                        />
                    </div>
                </div>
            </div>
            <FlashToast />
            <PwaInstallPrompt />
            <NotificationsPanel
                :open="showNotificationsPanel"
                @update:open="showNotificationsPanel = $event"
                @unread-count-update="onNotificationsUnreadCountUpdate"
            />
            <MobileBottomNav />
            <div
                class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-zinc-800"
            >
                <main class="flex-1 px-4 pb-24 pt-4 md:px-6 md:pt-6 lg:pb-8">
                    <div
                        class="w-full"
                        :class="[
                            layoutContentFlushLeft ? 'max-w-none lg:-ml-6' : 'mx-auto',
                            !layoutContentFlushLeft && contentMaxWidth,
                        ]"
                    >
                        <slot />
                        <slot name="content-footer" />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
