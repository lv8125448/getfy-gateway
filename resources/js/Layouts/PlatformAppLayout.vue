<script setup>
import { computed, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useSidebarProvider } from '@/composables/useSidebar';
import PlatformSidebar from '@/components/layout/PlatformSidebar.vue';
import PlatformAppHeader from '@/components/layout/PlatformAppHeader.vue';
import Backdrop from '@/components/layout/Backdrop.vue';
import FlashToast from '@/components/layout/FlashToast.vue';

const { isExpanded } = useSidebarProvider();
const page = usePage();
const contentMaxWidth = computed(() => (page.props.layoutFullWidth ? 'max-w-[1600px]' : 'max-w-7xl'));
const layoutContentFlushLeft = computed(() => !!page.props.layoutContentFlushLeft);

watchEffect(() => {
    const primary = page.props.appSettings?.theme_primary || '#0ea5e9';
    document.documentElement.style.setProperty('--color-primary', primary);
});
</script>

<template>
    <div class="min-h-screen bg-zinc-100 dark:bg-zinc-900">
        <PlatformSidebar />
        <Backdrop />
        <div
            class="flex min-h-screen flex-col transition-all duration-300 ease-in-out p-3 md:p-4 lg:p-6"
            :class="[isExpanded ? 'lg:ml-[260px]' : 'lg:ml-[72px]']"
        >
            <div class="flex w-full shrink-0 flex-col gap-2">
                <PlatformAppHeader />
            </div>
            <FlashToast />
            <div
                class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-zinc-800"
            >
                <main class="flex-1 px-4 pb-12 pt-4 md:px-6 md:pt-6 lg:pb-8">
                    <div
                        class="w-full"
                        :class="[
                            layoutContentFlushLeft ? 'max-w-none lg:-ml-6' : 'mx-auto',
                            !layoutContentFlushLeft && contentMaxWidth,
                        ]"
                    >
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
