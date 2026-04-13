<script setup>
import { onMounted, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { Upload, Plus, Trash2, ArrowUp, ArrowDown } from 'lucide-vue-next';

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const items = ref([]);
const uploadingKey = ref('');

function uid() {
    return `banner_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
}

function normalize(list) {
    return (Array.isArray(list) ? list : []).map((item, index) => ({
        id: item?.id || uid(),
        title: item?.title || '',
        desktop_url: item?.desktop_url || '',
        mobile_url: item?.mobile_url || '',
        active: item?.active !== false,
        sort_order: Number.isFinite(Number(item?.sort_order)) ? Number(item.sort_order) : index + 1,
    }));
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await window.axios.get('/plataforma/configuracoes/banners-dashboard/data');
        items.value = normalize(res.data?.banners || []);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Nao foi possível carregar os banners da dashboard.';
    } finally {
        loading.value = false;
    }
}

function addItem() {
    items.value.push({
        id: uid(),
        title: '',
        desktop_url: '',
        mobile_url: '',
        active: true,
        sort_order: items.value.length + 1,
    });
}

function removeItem(index) {
    items.value.splice(index, 1);
    items.value.forEach((item, idx) => {
        item.sort_order = idx + 1;
    });
}

function moveItem(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= items.value.length) return;
    const temp = items.value[index];
    items.value[index] = items.value[target];
    items.value[target] = temp;
    items.value.forEach((item, idx) => {
        item.sort_order = idx + 1;
    });
}

async function uploadImage(event, item, variant) {
    const file = event.target?.files?.[0];
    event.target.value = '';
    if (!file) return;

    const key = `${item.id}-${variant}`;
    uploadingKey.value = key;
    error.value = '';

    const fd = new FormData();
    fd.append('file', file);
    fd.append('variant', variant);

    try {
        const res = await window.axios.post('/plataforma/configuracoes/banners-dashboard/upload', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const url = res.data?.url || '';
        if (variant === 'desktop') item.desktop_url = url;
        if (variant === 'mobile') item.mobile_url = url;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao enviar imagem do banner.';
    } finally {
        uploadingKey.value = '';
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    try {
        const payload = items.value.map((item, index) => ({
            id: item.id || uid(),
            title: item.title || '',
            desktop_url: item.desktop_url || '',
            mobile_url: item.mobile_url || '',
            active: !!item.active,
            sort_order: index + 1,
        }));
        await window.axios.put('/plataforma/configuracoes/banners-dashboard', { banners: payload });
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao salvar banners da dashboard.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Banner da Dashboard</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Exibido abaixo do header e acima dos cards. Carrossel automático com desktop e mobile por slide.
                </p>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    Dimensões recomendadas: Desktop 1600x320px | Mobile 1200x420px.
                </p>
            </div>
            <Button type="button" class="inline-flex items-center gap-2" @click="addItem">
                <Plus class="h-4 w-4" />
                Adicionar banner
            </Button>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ error }}
        </p>

        <div v-if="loading" class="mt-6 text-sm text-zinc-500">Carregando banners...</div>

        <div v-else class="mt-6 space-y-4">
            <div
                v-for="(item, index) in items"
                :key="item.id"
                class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-600"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <label class="block text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Título (opcional)</label>
                        <input
                            v-model="item.title"
                            type="text"
                            class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                            placeholder="Ex.: Campanha de abril"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="moveItem(index, -1)">
                            <ArrowUp class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="moveItem(index, 1)">
                            <ArrowDown class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded p-2 text-zinc-500 hover:bg-zinc-100 hover:text-red-600 dark:hover:bg-zinc-800" @click="removeItem(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <label class="mb-3 flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <input v-model="item.active" type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]" />
                    Banner ativo
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-600">
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Desktop</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">1600x320px (faixa panorâmica)</p>
                        <div v-if="item.desktop_url" class="mt-3">
                            <img :src="item.desktop_url" alt="Banner desktop" class="h-28 w-full rounded object-cover" />
                        </div>
                        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-[var(--color-primary)]">
                            <Upload class="h-4 w-4" />
                            {{ item.desktop_url ? 'Substituir desktop' : 'Enviar desktop' }}
                            <input type="file" accept="image/*" class="hidden" @change="(e) => uploadImage(e, item, 'desktop')" />
                        </label>
                        <p v-if="uploadingKey === `${item.id}-desktop`" class="mt-1 text-xs text-zinc-500">Enviando...</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-600">
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Mobile</p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">1200x420px (faixa paisagem)</p>
                        <div v-if="item.mobile_url" class="mt-3 flex">
                            <img :src="item.mobile_url" alt="Banner mobile" class="h-20 w-36 rounded object-cover" />
                        </div>
                        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-[var(--color-primary)]">
                            <Upload class="h-4 w-4" />
                            {{ item.mobile_url ? 'Substituir mobile' : 'Enviar mobile' }}
                            <input type="file" accept="image/*" class="hidden" @change="(e) => uploadImage(e, item, 'mobile')" />
                        </label>
                        <p v-if="uploadingKey === `${item.id}-mobile`" class="mt-1 text-xs text-zinc-500">Enviando...</p>
                    </div>
                </div>
            </div>

            <div v-if="!items.length" class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:bg-zinc-800/40">
                Nenhum banner cadastrado.
            </div>

            <div class="pt-2">
                <Button type="button" :disabled="saving" @click="save">
                    {{ saving ? 'Salvando...' : 'Salvar banners da dashboard' }}
                </Button>
            </div>
        </div>
    </section>
</template>
