<script setup>
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';

defineOptions({ layout: LayoutInfoprodutor });

defineProps({
    pluginSlug: { type: String, default: '' },
    pluginPage: { type: String, default: 'Index' },
});
</script>

<template>
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Plugin Example</h1>
        <p class="text-zinc-600 dark:text-zinc-400">
            Página de exemplo do plugin (slug: {{ pluginSlug }}, página: {{ pluginPage }}).
        </p>
    </div>
</template>
