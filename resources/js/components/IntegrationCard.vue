<script setup>
import { computed } from 'vue';
import { Settings } from 'lucide-vue-next';

const props = defineProps({
  title: { type: String, required: true },
  logo: { type: String, required: true },
  description: { type: String, default: '' },
  selected: { type: Boolean, default: false },
  configured: { type: Boolean, default: false },
});
const emit = defineEmits(['select', 'configure']);

const ariaPressed = computed(() => (props.selected ? 'true' : 'false'));
</script>

<template>
  <div
    class="relative flex items-stretch rounded-xl border transition-all"
    :class="props.selected ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/20 bg-[var(--color-primary)]/5' : 'border-zinc-200 dark:border-zinc-700'"
  >
    <button
      type="button"
      class="flex-1 flex flex-col items-center justify-center gap-2 rounded-xl py-5 text-center transition-all hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[var(--color-primary)]/30 min-w-0"
      :class="selected ? 'px-10' : 'px-4'"
      :aria-pressed="ariaPressed"
      @click="$emit('select')"
    >
      <img :src="logo" alt="" class="h-10 w-auto rounded-lg object-contain" />
      <div class="text-sm font-semibold text-zinc-800 dark:text-white">{{ title }}</div>
      <div v-if="description" class="text-xs text-zinc-500 dark:text-zinc-400">{{ description }}</div>
      <div
        v-if="configured"
        class="mt-1 inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/30 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400"
        title="Configurado"
      >
        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
        </svg>
        Configurado
      </div>
    </button>

    <div
      v-if="selected"
      class="absolute right-2 top-2 z-10 flex items-center justify-center"
    >
      <button
        type="button"
        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200 transition-colors"
        title="Configurar provedor"
        @click.stop.prevent="$emit('configure')"
      >
        <Settings class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>

