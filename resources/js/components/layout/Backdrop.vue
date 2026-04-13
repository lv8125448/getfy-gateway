<script setup>
import { useSidebar } from '@/composables/useSidebar';

const { toggleMobileSidebar, isMobileOpen } = useSidebar();
</script>

<template>
    <div
        v-if="isMobileOpen"
        class="fixed inset-0 z-[9999] bg-zinc-900/50 lg:hidden"
        aria-hidden="true"
        @click="toggleMobileSidebar"
    />
</template>
