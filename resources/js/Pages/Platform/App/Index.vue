<script setup>
import { onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import Button from '@/components/ui/Button.vue';
import { Upload, Trash2, Send } from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const props = defineProps({
    app: { type: Object, required: true },
    push_subscriptions_count: { type: Number, default: 0 },
});

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const uploading = ref(false);
const uploadField = ref(null);
const sendingPush = ref(false);
const pushResult = ref(null);

const form = reactive({
    app_name: '',
    pwa_theme_color: '',
    pwa_icon_192: '',
    pwa_icon_512: '',
});

const pushForm = reactive({
    title: '',
    body: '',
    url: '',
});

const fieldLabels = {
    pwa_icon_192: 'Ícone PWA 192x192',
    pwa_icon_512: 'Ícone PWA 512x512',
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await window.axios.get('/plataforma/app/data');
        const app = res.data?.app ?? props.app ?? {};
        form.app_name = app.app_name || '';
        form.pwa_theme_color = app.pwa_theme_color || '';
        form.pwa_icon_192 = app.pwa_icon_192 || '';
        form.pwa_icon_512 = app.pwa_icon_512 || '';
    } catch (e) {
        error.value = e?.response?.data?.message || 'Nao foi possível carregar configurações do App.';
    } finally {
        loading.value = false;
    }
}

async function savePwa() {
    saving.value = true;
    error.value = '';
    try {
        await window.axios.put('/plataforma/app', { ...form });
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao salvar configurações do App.';
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
        const res = await window.axios.post('/plataforma/app/upload', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.field && res.data?.url) {
            form[res.data.field] = res.data.url;
        }
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao enviar ícone.';
    } finally {
        uploading.value = false;
        uploadField.value = null;
    }
}

async function clearField(field) {
    error.value = '';
    try {
        await window.axios.post('/plataforma/app/clear-field', { field });
        form[field] = '';
        await router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao remover ícone.';
    }
}

async function sendPush() {
    sendingPush.value = true;
    error.value = '';
    pushResult.value = null;
    try {
        const res = await window.axios.post('/plataforma/app/push/send', { ...pushForm });
        pushResult.value = res.data?.result ?? null;
        pushForm.title = '';
        pushForm.body = '';
        pushForm.url = '';
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao enviar notificação push.';
    } finally {
        sendingPush.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">App</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Configure PWA e envie notificações push para todos os assinantes do painel.
            </p>
        </div>

        <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
            {{ error }}
        </p>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">PWA</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Defina o nome, a cor e os ícones exibidos na instalação do App.
            </p>

            <div v-if="loading" class="mt-5 text-sm text-zinc-500">Carregando...</div>
            <div v-else class="mt-5 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome do App</label>
                        <input
                            v-model="form.app_name"
                            type="text"
                            class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                            placeholder="Ex.: Minha plataforma"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Cor do tema PWA</label>
                        <div class="mt-1.5 flex gap-2">
                            <input
                                v-model="form.pwa_theme_color"
                                type="text"
                                class="block min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 font-mono text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                                placeholder="#0ea5e9"
                            />
                            <input v-model="form.pwa_theme_color" type="color" class="h-11 w-14 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div
                        v-for="field in ['pwa_icon_192', 'pwa_icon_512']"
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

                <div>
                    <Button type="button" :disabled="saving" @click="savePwa">
                        {{ saving ? 'Salvando...' : 'Salvar PWA' }}
                    </Button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Push personalizado</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Envie notificação para todos os assinantes do painel. Inscrições atuais: <strong>{{ push_subscriptions_count }}</strong>.
            </p>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Título</label>
                    <input
                        v-model="pushForm.title"
                        type="text"
                        class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                        placeholder="Ex.: Aviso importante"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Mensagem</label>
                    <textarea
                        v-model="pushForm.body"
                        rows="3"
                        class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                        placeholder="Digite o conteúdo da notificação"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">URL de clique (opcional)</label>
                    <input
                        v-model="pushForm.url"
                        type="text"
                        class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                        placeholder="https://seu-dominio.com/plataforma/..."
                    />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button type="button" :disabled="sendingPush" class="inline-flex items-center gap-2" @click="sendPush">
                        <Send class="h-4 w-4" />
                        {{ sendingPush ? 'Enviando...' : 'Enviar push' }}
                    </Button>
                    <p v-if="pushResult" class="text-sm text-zinc-600 dark:text-zinc-400">
                        Enviados: <strong>{{ pushResult.sent }}</strong> /
                        Total: <strong>{{ pushResult.total }}</strong> |
                        Falhas: <strong>{{ pushResult.failed }}</strong> |
                        Inválidos: <strong>{{ pushResult.invalid }}</strong> |
                        Expirados: <strong>{{ pushResult.expired }}</strong>
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
