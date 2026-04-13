<script setup>
import { computed } from 'vue';
import { PanelsTopLeft } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { useSidebar } from '@/composables/useSidebar';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import UserMenu from '@/components/layout/UserMenu.vue';

const page = usePage();
const pageTitle = computed(() => page.props.pageTitle ?? null);

const { toggleSidebar, isMobileOpen, isMobile } = useSidebar();
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
            <h1 v-if="pageTitle" class="truncate text-xl font-semibold text-zinc-900 dark:text-white md:text-2xl">
                {{ pageTitle }}
            </h1>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <ThemeToggler />
            <UserMenu />
        </div>
    </header>
</template>
