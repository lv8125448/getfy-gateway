<script setup>
import { computed } from 'vue';
import { Zap } from 'lucide-vue-next';

const props = defineProps({
    app: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['click']);

const imageUrl = computed(() => {
    const img = props.app.image;
    if (!img) return null;
    if (img.startsWith('http') || img.startsWith('//')) return img;
    return `/${img.replace(/^\//, '')}`;
});
</script>

<template>
    <button
        type="button"
        class="group flex w-full flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white text-left shadow-sm transition-all duration-200 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
        @click="emit('click')"
    >
        <div
            class="flex h-24 shrink-0 items-center justify-center overflow-hidden rounded-t-2xl border-b border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/80"
        >
            <div
                v-if="imageUrl"
                class="flex size-full items-center justify-center"
            >
                <img
                    :src="imageUrl"
                    :alt="app.name"
                    class="max-h-full max-w-full rounded-2xl object-contain"
                    @error="($e) => ($e.target.style.display = 'none')"
                />
            </div>
            <Zap
                v-else
                class="h-10 w-10 text-zinc-400 dark:text-zinc-500"
                aria-hidden="true"
            />
        </div>
        <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="font-semibold text-zinc-900 dark:text-white">
                {{ app.name }}
            </div>
            <p
                v-if="app.description"
                class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400"
            >
                {{ app.description }}
            </p>
            <span
                v-if="app.status"
                class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="
                    app.status === 'active'
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                        : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400'
                "
            >
                {{ app.status === 'active' ? 'Ativo' : app.status }}
            </span>
        </div>
    </button>
</template>
