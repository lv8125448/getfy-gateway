<script setup>
import { ref, watch, computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import { X, ChevronUp, ChevronDown, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    method: { type: String, default: '' },
    methodLabel: { type: String, default: '' },
    primarySlug: { type: String, default: '' },
    gateways: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'update:modelValue', 'save']);

const localList = ref([]);

watch(
    () => [props.open, props.modelValue],
    ([open, val]) => {
        if (open) {
            localList.value = Array.isArray(val) ? [...val] : [];
        }
    },
    { immediate: true }
);

const availableToAdd = computed(() => {
    const inList = new Set([props.primarySlug, ...localList.value]);
    return (props.gateways || []).filter((g) => g.slug && !inList.has(g.slug));
});

function moveUp(index) {
    if (index <= 0) return;
    const arr = [...localList.value];
    [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
    localList.value = arr;
}

function moveDown(index) {
    if (index >= localList.value.length - 1) return;
    const arr = [...localList.value];
    [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
    localList.value = arr;
}

function remove(index) {
    localList.value = localList.value.filter((_, i) => i !== index);
}

function addGateway(slug) {
    if (!slug) return;
    localList.value = [...localList.value, slug];
}

function save() {
    emit('update:modelValue', [...localList.value]);
    emit('save', [...localList.value]);
    emit('close');
}

function close() {
    emit('close');
}

function gatewayName(slug) {
    const g = (props.gateways || []).find((x) => x.slug === slug);
    return g?.name ?? slug;
}
</script>

<template>
    <Teleport to="body">
        <div
            v-show="open"
            class="fixed inset-0 z-[100000] flex justify-end"
            aria-modal="true"
            role="dialog"
        >
            <div
                class="fixed inset-0 bg-zinc-900/50 dark:bg-zinc-950/60"
                aria-hidden="true"
                @click="close"
            />
            <aside
                class="relative flex h-full w-full max-w-md flex-col rounded-l-2xl bg-white shadow-xl dark:bg-zinc-900"
            >
                <div
                    class="flex items-center justify-between rounded-tl-2xl border-b border-zinc-200 px-4 py-4 dark:border-zinc-700"
                >
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        Redundância – {{ methodLabel }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex flex-1 flex-col overflow-y-auto p-4">
                    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                        Se o gateway principal falhar, serão tentados os gateways abaixo, na ordem.
                    </p>

                    <div class="space-y-2">
                        <div
                            v-for="(slug, index) in localList"
                            :key="slug"
                            class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800/50"
                        >
                            <span class="w-6 shrink-0 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                                {{ index + 1 }}.
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-900 dark:text-white">
                                {{ gatewayName(slug) }}
                            </span>
                            <div class="flex shrink-0 items-center gap-0.5">
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-300 disabled:opacity-40"
                                    :disabled="index === 0"
                                    aria-label="Subir"
                                    @click="moveUp(index)"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-300 disabled:opacity-40"
                                    :disabled="index === localList.length - 1"
                                    aria-label="Descer"
                                    @click="moveDown(index)"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                    aria-label="Remover"
                                    @click="remove(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="availableToAdd.length > 0" class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Adicionar gateway
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="g in availableToAdd"
                                :key="g.slug"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 transition hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/10 hover:text-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-[var(--color-primary)] dark:hover:bg-[var(--color-primary)]/20"
                                @click="addGateway(g.slug)"
                            >
                                <Plus class="h-4 w-4" />
                                {{ g.name }}
                            </button>
                        </div>
                    </div>

                    <div v-else-if="localList.length === 0" class="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 p-6 text-center dark:border-zinc-600 dark:bg-zinc-800/30">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Nenhum gateway de redundância configurado. Adicione gateways acima quando houver opções disponíveis.
                        </p>
                    </div>

                    <div class="mt-6 flex flex-col gap-2">
                        <Button @click="save">
                            Salvar
                        </Button>
                        <Button variant="outline" @click="close">
                            Cancelar
                        </Button>
                    </div>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
