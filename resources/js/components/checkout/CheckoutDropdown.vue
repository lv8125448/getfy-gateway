<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    /** Ícone (componente Vue ou função de componente) exibido no trigger quando não usa slot #trigger */
    icon: { type: [Object, Function], default: null },
    /** Label para acessibilidade */
    ariaLabel: { type: String, default: 'Opções' },
    /** Se o dropdown está aberto (controlado) ou use v-model:open */
    open: { type: Boolean, default: undefined },
    /** Alinhamento do painel: 'right' | 'left' */
    align: { type: String, default: 'right' },
    /** Se true, renderiza o painel em document.body (evita corte por overflow) */
    teleport: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

const isOpen = ref(false);
const isControlled = typeof props.open === 'boolean';
const openState = computed(() => (isControlled ? props.open : isOpen.value));

const triggerRef = ref(null);
const panelRef = ref(null);
const panelStyle = ref({});

function toggle() {
    if (isControlled) {
        emit('update:open', !props.open);
    } else {
        isOpen.value = !isOpen.value;
    }
}

function close() {
    if (isControlled) {
        emit('update:open', false);
    } else {
        isOpen.value = false;
    }
}

function updatePanelPosition() {
    if (!props.teleport || !triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    panelStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: props.align === 'right' ? 'auto' : `${rect.left}px`,
        right: props.align === 'right' ? `${window.innerWidth - rect.right}px` : 'auto',
        zIndex: 50,
    };
}

function onClickOutside(e) {
    if (!triggerRef.value?.contains(e.target) && !panelRef.value?.contains(e.target)) {
        close();
    }
}

watch(openState, (v) => {
    if (v) {
        requestAnimationFrame(() => document.addEventListener('click', onClickOutside, true));
        if (props.teleport) {
            nextTick(() => {
                updatePanelPosition();
                window.addEventListener('resize', updatePanelPosition);
                window.addEventListener('scroll', updatePanelPosition, true);
            });
        }
    } else {
        document.removeEventListener('click', onClickOutside, true);
        if (props.teleport) {
            window.removeEventListener('resize', updatePanelPosition);
            window.removeEventListener('scroll', updatePanelPosition, true);
        }
    }
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside, true);
    if (props.teleport) {
        window.removeEventListener('resize', updatePanelPosition);
        window.removeEventListener('scroll', updatePanelPosition, true);
    }
});

const panelClasses = computed(() => {
    const base = 'z-50 max-h-60 min-w-[10rem] overflow-y-auto rounded-xl border border-gray-200 bg-white py-1.5 shadow-lg shadow-gray-200/80';
    if (props.teleport) return base;
    return [
        base,
        'absolute top-full mt-2',
        props.align === 'right' ? 'right-0' : 'left-0',
    ].join(' ');
});
</script>

<template>
    <div class="relative inline-block">
        <div ref="triggerRef" class="flex h-full min-h-9 cursor-pointer items-center" @click="toggle">
            <slot name="trigger" :open="openState">
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-200 focus:ring-offset-1"
                    :class="{ 'bg-gray-100 text-gray-700': openState }"
                    :aria-label="ariaLabel"
                    :aria-expanded="openState"
                >
                    <component v-if="icon" :is="icon" class="h-5 w-5" aria-hidden="true" />
                </button>
            </slot>
        </div>
        <Teleport to="body" :disabled="!teleport">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-show="openState"
                    ref="panelRef"
                    :class="panelClasses"
                    :style="teleport ? panelStyle : undefined"
                    role="listbox"
                >
                    <slot />
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
