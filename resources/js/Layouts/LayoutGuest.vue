<script setup>
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
</script>

<template>
    <div class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 transition-colors">
        <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
                <span class="text-lg font-semibold text-zinc-900 dark:text-white">
                    Infoprodutor
                </span>
                <ThemeToggler />
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
