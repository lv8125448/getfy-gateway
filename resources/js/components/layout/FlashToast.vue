<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, X } from 'lucide-vue-next';

const page = usePage();
const dismissed = ref(false);
let dismissTimer = null;

const flash = computed(() => page.props.flash ?? { success: null, error: null });

const message = computed(() => flash.value?.error ?? flash.value?.success ?? null);
const isError = computed(() => !!flash.value?.error);

const visible = computed(() => !!message.value && !dismissed.value);

function close() {
    dismissed.value = true;
    if (dismissTimer) {
        clearTimeout(dismissTimer);
        dismissTimer = null;
    }
}

watch(
    () => [flash.value?.success, flash.value?.error],
    () => {
        dismissed.value = false;
    },
    { immediate: true }
);

watch(
    visible,
    (v) => {
        if (v && message.value) {
            if (dismissTimer) clearTimeout(dismissTimer);
            dismissTimer = setTimeout(close, 4500);
        }
    },
    { immediate: true }
);

onUnmounted(() => {
    if (dismissTimer) clearTimeout(dismissTimer);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-if="visible"
                role="alert"
                aria-live="polite"
                class="fixed bottom-4 right-4 z-[100001] flex max-w-sm items-start gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
            >
                <span
                    :class="[
                        'shrink-0 rounded-full p-0.5',
                        isError ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400',
                    ]"
                >
                    <XCircle v-if="isError" class="h-5 w-5" aria-hidden="true" />
                    <CheckCircle2 v-else class="h-5 w-5" aria-hidden="true" />
                </span>
                <p
                    :class="[
                        'min-w-0 flex-1 text-sm font-medium',
                        isError ? 'text-red-800 dark:text-red-200' : 'text-zinc-800 dark:text-zinc-200',
                    ]"
                >
                    {{ message }}
                </p>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                    aria-label="Fechar"
                    @click="close"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
