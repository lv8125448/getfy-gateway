<script setup>
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'radix-vue';
import { ChevronDown, Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const VALUE_NONE = '__none__';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Selecione...' },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// Radix usa valor interno; '' mapeamos para VALUE_NONE para evitar bugs
const internalValue = computed({
    get: () => (props.modelValue === '' ? VALUE_NONE : props.modelValue),
    set: (v) => emit('update:modelValue', v === VALUE_NONE ? '' : v),
});

// Opções com '' substituído por VALUE_NONE para o Select
const selectOptions = computed(() =>
    props.options.map((opt) => ({
        value: opt.value === '' ? VALUE_NONE : opt.value,
        label: opt.label,
    }))
);

const triggerClass = cn(
    'flex h-12 w-full cursor-pointer items-center justify-between gap-2 rounded-xl border-2 border-zinc-200 bg-white px-4 py-3 text-left text-sm transition',
    'hover:border-zinc-300 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:ring-offset-0',
    'data-[placeholder]:text-zinc-500',
    'dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:hover:border-zinc-500 dark:data-[placeholder]:text-zinc-400'
);
</script>

<template>
    <SelectRoot
        v-model="internalValue"
        :disabled="disabled"
    >
        <SelectTrigger
            :class="triggerClass"
            type="button"
            :aria-label="label || placeholder"
        >
            <SelectValue :placeholder="placeholder" />
            <ChevronDown
                class="h-5 w-5 shrink-0 text-zinc-400 dark:text-zinc-500"
                aria-hidden="true"
            />
        </SelectTrigger>
        <SelectPortal to="body">
            <SelectContent
                class="z-[9999] min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-xl border border-zinc-200 bg-white py-1 shadow-xl dark:border-zinc-600 dark:bg-zinc-800"
                :side-offset="4"
                position="popper"
                :avoid-collisions="true"
            >
                <SelectViewport class="p-1">
                    <SelectItem
                        v-for="opt in selectOptions"
                        :key="String(opt.value)"
                        :value="opt.value"
                        class="relative flex cursor-pointer select-none items-center rounded-lg py-2.5 pl-10 pr-4 text-sm outline-none transition data-[highlighted]:bg-[var(--color-primary)]/10 data-[highlighted]:text-[var(--color-primary)] data-[state=checked]:bg-[var(--color-primary)]/10 data-[state=checked]:text-[var(--color-primary)] dark:data-[highlighted]:bg-[var(--color-primary)]/20 dark:data-[state=checked]:bg-[var(--color-primary)]/20"
                    >
                        <SelectItemIndicator class="absolute left-3 flex h-4 w-4 items-center justify-center">
                            <Check class="h-4 w-4 text-[var(--color-primary)]" />
                        </SelectItemIndicator>
                        <SelectItemText>{{ opt.label }}</SelectItemText>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
