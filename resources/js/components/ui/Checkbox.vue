<script setup>
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    class: { type: [String, Object, Array], default: '' },
});

const emit = defineEmits(['update:modelValue']);

function toggle() {
    if (props.disabled) return;
    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <label
        :class="[
            'inline-flex w-auto max-w-full cursor-pointer items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300 transition-colors',
            disabled && 'cursor-not-allowed opacity-60',
            cn(props.class),
        ]"
        @click="toggle"
    >
        <span
            role="checkbox"
            :aria-checked="modelValue"
            :aria-label="label || (modelValue ? 'Marcado' : 'Desmarcado')"
            :class="[
                'relative flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition-all duration-200',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900',
                modelValue
                    ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                    : 'border-zinc-300 bg-white hover:border-zinc-400 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:border-zinc-500',
                disabled && 'pointer-events-none',
            ]"
            tabindex="0"
            @keydown.enter.prevent="toggle"
            @keydown.space.prevent="toggle"
        >
            <Check v-if="modelValue" class="h-3 w-3 stroke-[2.5]" />
        </span>
        <span v-if="label" class="select-none">{{ label }}</span>
        <slot v-else />
    </label>
</template>
