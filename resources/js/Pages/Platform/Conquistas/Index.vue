<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import Button from '@/components/ui/Button.vue';
import { Upload, Trash2, Save, Plus } from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const props = defineProps({
    achievements: {
        type: Array,
        default: () => [],
    },
});

const items = ref((props.achievements || []).map((a) => ({ ...a })));
const savingId = ref(null);
const deletingId = ref(null);
const uploadingId = ref(null);
const creating = ref(false);
const error = ref('');

const createForm = reactive({
    slug: '',
    name: '',
    threshold: 0,
    image: '',
    sort_order: 0,
    is_active: true,
});

function normalizeSlug(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/--+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function onNameInputForCreate() {
    if (!createForm.slug) {
        createForm.slug = normalizeSlug(createForm.name);
    }
}

async function createAchievement() {
    creating.value = true;
    error.value = '';
    try {
        const payload = {
            ...createForm,
            slug: normalizeSlug(createForm.slug),
        };
        await window.axios.post('/plataforma/conquistas', payload);
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao criar conquista.';
    } finally {
        creating.value = false;
    }
}

async function saveItem(item) {
    savingId.value = item.id;
    error.value = '';
    try {
        await window.axios.put(`/plataforma/conquistas/${item.id}`, {
            slug: normalizeSlug(item.slug),
            name: item.name,
            threshold: Number(item.threshold) || 0,
            image: item.image || null,
            sort_order: Number(item.sort_order) || 0,
            is_active: !!item.is_active,
        });
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao salvar conquista.';
    } finally {
        savingId.value = null;
    }
}

async function deleteItem(item) {
    if (!window.confirm(`Remover a conquista "${item.name}"?`)) return;
    deletingId.value = item.id;
    error.value = '';
    try {
        await window.axios.delete(`/plataforma/conquistas/${item.id}`);
        items.value = items.value.filter((x) => x.id !== item.id);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao remover conquista.';
    } finally {
        deletingId.value = null;
    }
}

async function onImageChange(event, item) {
    const file = event.target?.files?.[0];
    event.target.value = '';
    if (!file) return;
    uploadingId.value = item.id;
    error.value = '';
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res = await window.axios.post(`/plataforma/conquistas/${item.id}/image`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        item.image = res.data?.url || item.image;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao enviar imagem.';
    } finally {
        uploadingId.value = null;
    }
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Conquistas</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Gerencie as conquistas de vendas do painel do infoprodutor.
            </p>
        </div>

        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
            {{ error }}
        </p>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Nova conquista</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-6">
                <input v-model="createForm.name" type="text" placeholder="Nome" class="rounded-xl border border-zinc-300 px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" @input="onNameInputForCreate" />
                <input v-model="createForm.slug" type="text" placeholder="slug" class="rounded-xl border border-zinc-300 px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                <input v-model.number="createForm.threshold" type="number" min="0" step="0.01" placeholder="Meta (R$)" class="rounded-xl border border-zinc-300 px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                <input v-model.number="createForm.sort_order" type="number" min="0" placeholder="Ordem" class="rounded-xl border border-zinc-300 px-3 py-2.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 px-3 py-2.5 text-sm dark:border-zinc-600 dark:text-zinc-200">
                    <input v-model="createForm.is_active" type="checkbox" />
                    Ativa
                </label>
                <Button type="button" class="inline-flex items-center gap-2" :disabled="creating" @click="createAchievement">
                    <Plus class="h-4 w-4" />
                    {{ creating ? 'Criando...' : 'Criar' }}
                </Button>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <th class="px-4 py-3">Imagem</th>
                            <th class="px-4 py-3">Nome</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Meta (R$)</th>
                            <th class="px-4 py-3">Ordem</th>
                            <th class="px-4 py-3">Ativa</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-b border-zinc-100 align-top dark:border-zinc-800">
                            <td class="px-4 py-3">
                                <div class="space-y-2">
                                    <img v-if="item.image" :src="item.image" :alt="item.name" class="h-12 w-12 rounded-lg object-cover" />
                                    <label class="inline-flex cursor-pointer items-center gap-1 text-xs text-[var(--color-primary)]">
                                        <Upload class="h-3.5 w-3.5" />
                                        {{ uploadingId === item.id ? 'Enviando...' : 'Upload' }}
                                        <input type="file" accept="image/*" class="hidden" @change="(e) => onImageChange(e, item)" />
                                    </label>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <input v-model="item.name" type="text" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            </td>
                            <td class="px-4 py-3">
                                <input v-model="item.slug" type="text" class="w-full rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            </td>
                            <td class="px-4 py-3">
                                <input v-model.number="item.threshold" type="number" min="0" step="0.01" class="w-32 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            </td>
                            <td class="px-4 py-3">
                                <input v-model.number="item.sort_order" type="number" min="0" class="w-20 rounded-lg border border-zinc-300 px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            </td>
                            <td class="px-4 py-3">
                                <input v-model="item.is_active" type="checkbox" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <Button type="button" size="sm" variant="outline" class="inline-flex items-center gap-1" :disabled="savingId === item.id" @click="saveItem(item)">
                                        <Save class="h-3.5 w-3.5" />
                                        {{ savingId === item.id ? 'Salvando...' : 'Salvar' }}
                                    </Button>
                                    <Button type="button" size="sm" variant="outline" class="inline-flex items-center gap-1 text-red-600" :disabled="deletingId === item.id" @click="deleteItem(item)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                        {{ deletingId === item.id ? 'Removendo...' : 'Remover' }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
