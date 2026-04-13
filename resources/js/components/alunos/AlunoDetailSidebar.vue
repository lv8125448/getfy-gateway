<script setup>
import { ref, watch } from 'vue';
import { X, Pencil, Trash2, Package, Loader2 } from 'lucide-vue-next';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    aluno: { type: Object, default: null },
    produtos: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const editing = ref(false);
const saving = ref(false);
const form = ref({
    name: '',
    email: '',
    password: '',
    product_ids: [],
});
const removingProductId = ref(null);
const deleting = ref(false);
const toast = ref({ message: null, type: null });

watch(
    () => props.aluno,
    (a) => {
        if (a) {
            form.value = {
                name: a.name ?? '',
                email: a.email ?? '',
                password: '',
                product_ids: (a.products ?? []).map((p) => p.id),
            };
        }
        editing.value = false;
    },
    { immediate: true }
);

function close() {
    emit('close');
}

function startEdit() {
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    if (props.aluno) {
        form.value = {
            name: props.aluno.name ?? '',
            email: props.aluno.email ?? '',
            password: '',
            product_ids: (props.aluno.products ?? []).map((p) => p.id),
        };
    }
}

async function save() {
    if (!props.aluno) return;
    saving.value = true;
    try {
        const { data } = await axios.put(`/produtos/alunos/${props.aluno.id}`, {
            name: form.value.name,
            email: form.value.email,
            password: form.value.password || undefined,
            product_ids: form.value.product_ids,
        });
        showToast(data.message ?? 'Aluno atualizado.', 'success');
        editing.value = false;
        emit('updated', data.aluno);
    } catch (err) {
        showToast(
            err.response?.data?.message ?? 'Erro ao atualizar. Tente novamente.',
            'error'
        );
    } finally {
        saving.value = false;
    }
}

async function removeProduct(produtoId) {
    if (!props.aluno) return;
    removingProductId.value = produtoId;
    try {
        const { data } = await axios.delete(
            `/produtos/alunos/${props.aluno.id}/produtos/${produtoId}`
        );
        showToast(data.message ?? 'Acesso removido.', 'success');
        emit('updated', {
            ...props.aluno,
            products_count: data.products_count ?? 0,
            products: (props.aluno.products ?? []).filter((p) => p.id !== produtoId),
        });
    } catch (err) {
        showToast(
            err.response?.data?.message ?? 'Erro ao remover acesso.',
            'error'
        );
    } finally {
        removingProductId.value = null;
    }
}

async function deleteAluno() {
    if (!props.aluno) return;
    if (!window.confirm('Tem certeza que deseja excluir este aluno? Esta ação não pode ser desfeita.')) {
        return;
    }
    deleting.value = true;
    try {
        await axios.delete(`/produtos/alunos/${props.aluno.id}`);
        showToast('Aluno excluído com sucesso.', 'success');
        close();
        emit('deleted', props.aluno.id);
    } catch (err) {
        showToast(
            err.response?.data?.message ?? 'Erro ao excluir.',
            'error'
        );
    } finally {
        deleting.value = false;
    }
}

function showToast(message, type) {
    toast.value = { message, type };
    setTimeout(() => {
        toast.value = { message: null, type: null };
    }, 4000);
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
                class="relative flex h-full w-full max-w-md flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900"
            >
                <div class="flex items-center justify-between rounded-tl-2xl px-5 py-5">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ editing ? 'Editar aluno' : 'Detalhes do aluno' }}
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

                <div v-if="!aluno" class="flex flex-1 items-center justify-center p-8">
                    <p class="text-sm text-zinc-500">Nenhum aluno selecionado.</p>
                </div>

                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex-1 overflow-y-auto p-5">
                        <div v-if="!editing" class="space-y-5">
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Nome
                                </p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ aluno.name }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    E-mail
                                </p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ aluno.email }}</p>
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Produtos com acesso
                                </p>
                                <div
                                    v-for="p in (aluno.products ?? [])"
                                    :key="p.id"
                                    class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 py-2 pl-3 pr-2 dark:border-zinc-700 dark:bg-zinc-800/50"
                                >
                                    <span class="flex items-center gap-2 text-sm text-zinc-900 dark:text-white">
                                        <Package class="h-4 w-4 text-zinc-500" />
                                        {{ p.name }}
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        :disabled="removingProductId === p.id"
                                        @click="removeProduct(p.id)"
                                    >
                                        {{ removingProductId === p.id ? 'Removendo...' : 'Remover' }}
                                    </button>
                                </div>
                                <p v-if="!aluno.products?.length" class="text-sm text-zinc-500">
                                    Nenhum produto
                                </p>
                            </div>
                            <div class="flex flex-col gap-2 pt-4">
                                <Button variant="outline" class="w-full justify-start" @click="startEdit">
                                    <Pencil class="h-4 w-4" />
                                    Editar
                                </Button>
                                <Button
                                    variant="destructive"
                                    class="w-full justify-start"
                                    :disabled="deleting"
                                    @click="deleteAluno"
                                >
                                    <Loader2 v-if="deleting" class="h-4 w-4 animate-spin" />
                                    <Trash2 v-else class="h-4 w-4" />
                                    Excluir aluno
                                </Button>
                            </div>
                        </div>

                        <div v-else class="space-y-5">
                            <div class="space-y-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Nome
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="Nome do aluno"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    E-mail
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="email@exemplo.com"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Nova senha (deixe em branco para manter)
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="••••••••"
                                />
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Produtos com acesso
                                </p>
                                <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <label
                                        v-for="p in produtos"
                                        :key="p.id"
                                        class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <span class="shrink-0 w-fit">
                                            <Checkbox
                                                :model-value="form.product_ids.includes(p.id)"
                                                @update:model-value="(v) => { if (v) form.product_ids = [...form.product_ids, p.id]; else form.product_ids = form.product_ids.filter(x => x !== p.id); }"
                                            />
                                        </span>
                                        <span class="flex-1 text-left text-sm text-zinc-900 dark:text-white">{{ p.name }}</span>
                                    </label>
                                    <p v-if="!produtos.length" class="text-sm text-zinc-500">
                                        Nenhum produto disponível
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-4">
                                <Button
                                    variant="primary"
                                    class="flex-1"
                                    :disabled="saving"
                                    @click="save"
                                >
                                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                                    Salvar
                                </Button>
                                <Button variant="outline" :disabled="saving" @click="cancelEdit">
                                    Cancelar
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Toast -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-2 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-2 opacity-0"
                    >
                        <div
                            v-if="toast.message"
                            role="alert"
                            :class="[
                                'mx-5 mb-5 rounded-xl border px-4 py-3 text-sm',
                                toast.type === 'error'
                                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200',
                            ]"
                        >
                            {{ toast.message }}
                        </div>
                    </Transition>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
