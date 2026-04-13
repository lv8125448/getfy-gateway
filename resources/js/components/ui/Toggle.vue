<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    name: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function toggle() {
    if (props.disabled) return;
    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <div class="flex items-center gap-3" :class="{ 'cursor-not-allowed opacity-60': disabled }">
        <button
            type="button"
            role="switch"
            :disabled="disabled"
            :aria-checked="modelValue"
            :aria-label="label || (modelValue ? 'Ativo' : 'Inativo')"
            :class="[
                'relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2 dark:focus:ring-offset-zinc-900',
                modelValue
                    ? 'bg-[var(--color-primary)]'
                    : 'bg-zinc-200 dark:bg-zinc-600',
            ]"
            @click="toggle"
        >
            <span
                :class="[
                    'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition',
                    modelValue ? 'translate-x-5' : 'translate-x-0.5',
                ]"
            />
        </button>
        <label v-if="label" class="text-sm font-medium text-zinc-700 dark:text-zinc-300" @click="toggle">
            {{ label }}
        </label>
    </div>
</template>
