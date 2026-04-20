<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { Upload, Trash2, Copy } from 'lucide-vue-next';

const loading = ref(true);
const saving = ref(false);
const syncing = ref(false);
const error = ref('');
const canSyncGlobal = ref(false);

const form = reactive({
    app_name: '',
    theme_primary: '',
    app_logo: '',
    app_logo_dark: '',
    app_logo_icon: '',
    app_logo_icon_dark: '',
    login_hero_image: '',
    favicon_url: '',
});

const uploadField = ref(null);
const uploading = ref(false);

function api(path) {
    const p = path.startsWith('/') ? path : `/${path}`;
    // Sempre same-origin: evita ERR_CONNECTION_REFUSED quando APP_URL (.env) é IP/host
    // interno mas o painel é acessado por domínio público (ex.: Cloudflare).
    return p;
}

const fieldLabels = {
    app_logo: 'Logo (tema claro)',
    app_logo_dark: 'Logo (tema escuro)',
    app_logo_icon: 'Logo colapsada (claro)',
    app_logo_icon_dark: 'Logo colapsada (escuro)',
    login_hero_image: 'Imagem da tela de login',
    favicon_url: 'Favicon (aba do navegador)',
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await window.axios.get(api('/plataforma/configuracoes/personalizacao/data'));
        canSyncGlobal.value = !!res.data?.can_sync_global;
        const b = res.data?.branding ?? {};
        Object.keys(form).forEach((k) => {
            form[k] = typeof b[k] === 'string' ? b[k] : '';
        });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Nao foi possivel carregar as configuracoes de personalizacao.';
    } finally {
        loading.value = false;
    }
}

async function saveText() {
    saving.value = true;
    error.value = '';
    try {
        await window.axios.put(api('/plataforma/configuracoes/personalizacao'), { ...form });
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao salvar.';
    } finally {
        saving.value = false;
    }
}

async function onFileChange(event, field) {
    const file = event.target?.files?.[0];
    event.target.value = '';
    if (!file) return;
    uploading.value = true;
    uploadField.value = field;
    error.value = '';
    const fd = new FormData();
    fd.append('field', field);
    fd.append('file', file);
    try {
        const res = await window.axios.post(api('/plataforma/configuracoes/personalizacao/upload'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.field && res.data?.url) {
            form[res.data.field] = res.data.url;
        }
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro no envio da imagem.';
    } finally {
        uploading.value = false;
        uploadField.value = null;
    }
}

async function clearField(field) {
    error.value = '';
    try {
        await window.axios.post(api('/plataforma/configuracoes/personalizacao/clear-field'), { field });
        form[field] = '';
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao remover.';
    }
}

async function syncGlobal() {
    syncing.value = true;
    error.value = '';
    try {
        await window.axios.post(api('/plataforma/configuracoes/personalizacao/sync-global'));
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Nao foi possivel copiar para o login global.';
    } finally {
        syncing.value = false;
    }
}

onMounted(() => {
    load();
});

const imageFields = [
    'app_logo',
    'app_logo_dark',
    'app_logo_icon',
    'app_logo_icon_dark',
    'login_hero_image',
    'favicon_url',
];
</script>

<template>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Personalização</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Ajuste nome da aplicação, cores e imagens. Valores vazios voltam ao padrão da plataforma ou ao registro global (login público).
            </p>

            <p v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
                {{ error }}
            </p>

            <div v-if="loading" class="mt-6 text-sm text-zinc-500">Carregando...</div>

            <div v-else class="mt-6 space-y-8">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome da aplicação</label>
                        <input
                            v-model="form.app_name"
                            type="text"
                            class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                            placeholder="Ex.: Minha marca"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cor primária</label>
                        <div class="mt-1.5 flex gap-2">
                            <input
                                v-model="form.theme_primary"
                                type="text"
                                class="block min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 font-mono text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                                placeholder="#00cc00"
                            />
                            <input v-model="form.theme_primary" type="color" class="h-11 w-14 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Imagens</h3>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div
                            v-for="field in imageFields"
                            :key="field"
                            class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-600"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ fieldLabels[field] }}</span>
                                <button
                                    v-if="form[field]"
                                    type="button"
                                    class="rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-red-600 dark:hover:bg-zinc-800"
                                    :title="'Remover ' + fieldLabels[field]"
                                    @click="clearField(field)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="form[field]" class="mt-3">
                                <img :src="form[field]" :alt="fieldLabels[field]" class="max-h-32 max-w-full rounded-lg object-contain" />
                            </div>
                            <label class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-[var(--color-primary)]">
                                <Upload class="h-4 w-4" />
                                <span>{{ form[field] ? 'Substituir' : 'Enviar' }} arquivo</span>
                                <input type="file" accept="image/*" class="hidden" @change="(e) => onFileChange(e, field)" />
                            </label>
                            <p v-if="uploading && uploadField === field" class="mt-2 text-xs text-zinc-500">Enviando...</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button type="button" :disabled="saving" @click="saveText">
                        {{ saving ? 'Salvando...' : 'Salvar texto e cores' }}
                    </Button>
                    <Button
                        v-if="canSyncGlobal"
                        type="button"
                        variant="outline"
                        class="inline-flex items-center gap-2"
                        :disabled="syncing"
                        @click="syncGlobal"
                    >
                        <Copy class="h-4 w-4" />
                        {{ syncing ? 'Copiando...' : 'Copiar para tela de login (global)' }}
                    </Button>
                </div>

                <p v-if="canSyncGlobal" class="text-xs text-zinc-500 dark:text-zinc-400">
                    O login e páginas públicas usam o registro <strong>global</strong>. Use o botão acima para replicar as configurações do seu tenant para todos os visitantes (apenas administradores).
                </p>
            </div>
        </section>
    </div>
</template>
