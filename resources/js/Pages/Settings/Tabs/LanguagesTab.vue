<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { Plus, Pencil, Check, X } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const newLanguage = reactive({
    code: '',
    name: '',
});
const editingLanguageId = ref(null);
const editingLanguageName = ref('');

const state = reactive({
    selectedLocale: 'pt_BR',
    defaultLocale: 'pt_BR',
    languages: [],
    keys: [],
    values: {},
    search: '',
});

const filteredKeys = computed(() => {
    const q = state.search.trim().toLowerCase();
    if (!q) return state.keys;
    return state.keys.filter((k) => String(k).toLowerCase().includes(q));
});

async function load(locale = null) {
    loading.value = true;
    error.value = '';
    try {
        const res = await window.axios.get('/plataforma/configuracoes/idiomas/data', {
            params: locale ? { locale } : {},
        });
        state.selectedLocale = res.data?.selected_locale || state.selectedLocale;
        state.defaultLocale = res.data?.default_locale || 'pt_BR';
        state.languages = res.data?.languages || [];
        state.keys = res.data?.keys || [];
        state.values = { ...(res.data?.values || {}) };
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao carregar idiomas.';
    } finally {
        loading.value = false;
    }
}

async function addLanguage() {
    if (!newLanguage.code.trim() || !newLanguage.name.trim()) return;
    error.value = '';
    try {
        await window.axios.post('/plataforma/configuracoes/idiomas/languages', {
            code: newLanguage.code.trim(),
            name: newLanguage.name.trim(),
        });
        newLanguage.code = '';
        newLanguage.name = '';
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao adicionar idioma.';
    }
}

async function setDefault(lang) {
    error.value = '';
    try {
        await window.axios.put(`/plataforma/configuracoes/idiomas/languages/${lang.id}`, {
            is_default: true,
        });
        await load(state.selectedLocale);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao definir idioma padrão.';
    }
}

async function toggleActive(lang) {
    error.value = '';
    try {
        await window.axios.put(`/plataforma/configuracoes/idiomas/languages/${lang.id}`, {
            is_active: !lang.is_active,
        });
        await load(state.selectedLocale);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao alterar status do idioma.';
    }
}

function startEditLanguage(lang) {
    editingLanguageId.value = lang.id;
    editingLanguageName.value = lang.name ?? '';
}

function cancelEditLanguage() {
    editingLanguageId.value = null;
    editingLanguageName.value = '';
}

async function saveLanguageName(lang) {
    if (!editingLanguageName.value.trim()) return;
    error.value = '';
    try {
        await window.axios.put(`/plataforma/configuracoes/idiomas/languages/${lang.id}`, {
            name: editingLanguageName.value.trim(),
        });
        cancelEditLanguage();
        await load(state.selectedLocale);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao editar nome do idioma.';
    }
}

async function saveTranslations() {
    saving.value = true;
    error.value = '';
    try {
        const payload = {};
        state.keys.forEach((key) => {
            payload[key] = state.values[key] ?? '';
        });
        await window.axios.put('/plataforma/configuracoes/idiomas/translations', {
            locale: state.selectedLocale,
            translations: payload,
        });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao salvar traduções.';
    } finally {
        saving.value = false;
    }
}

async function importMissing() {
    error.value = '';
    try {
        await window.axios.post('/plataforma/configuracoes/idiomas/import-missing', {
            locale: state.selectedLocale,
        });
        await load(state.selectedLocale);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao importar chaves faltantes.';
    }
}

onMounted(() => load());
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ t('settings.languages.title', 'Idiomas da plataforma') }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ t('settings.languages.subtitle', 'Gerencie os idiomas e traduções do painel do infoprodutor.') }}
                </p>
            </div>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ error }}
        </p>

        <div class="mt-5 grid gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ t('settings.languages.add', 'Adicionar idioma') }}</p>
            <div class="grid gap-3 md:grid-cols-3">
                <input
                    v-model="newLanguage.code"
                    type="text"
                    class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                    placeholder="en"
                />
                <input
                    v-model="newLanguage.name"
                    type="text"
                    class="rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                    placeholder="English"
                />
                <Button type="button" class="inline-flex items-center justify-center gap-2" @click="addLanguage">
                    <Plus class="h-4 w-4" />
                    {{ t('settings.languages.add', 'Adicionar idioma') }}
                </Button>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">Idiomas</p>
                <div class="space-y-2">
                    <button
                        v-for="lang in state.languages"
                        :key="lang.id"
                        type="button"
                        class="w-full rounded-lg border px-3 py-2 text-left text-sm transition"
                        :class="state.selectedLocale === lang.code
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-zinc-900 dark:text-white'
                            : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                        @click="load(lang.code)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span v-if="editingLanguageId !== lang.id">{{ lang.name }} ({{ lang.code }})</span>
                            <div v-else class="flex min-w-0 flex-1 items-center gap-2">
                                <input
                                    v-model="editingLanguageName"
                                    type="text"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                                    :placeholder="lang.code"
                                    @click.stop
                                />
                            </div>
                            <span v-if="lang.is_default" class="text-xs text-[var(--color-primary)]">{{ t('settings.languages.default', 'Padrão') }}</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" class="rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800" @click.stop="setDefault(lang)">
                                {{ t('settings.languages.default', 'Padrão') }}
                            </button>
                            <button type="button" class="rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800" @click.stop="toggleActive(lang)">
                                {{ lang.is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button
                                v-if="editingLanguageId !== lang.id"
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800"
                                @click.stop="startEditLanguage(lang)"
                            >
                                <Pencil class="h-3 w-3" />
                                Editar nome
                            </button>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-1 text-xs text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                @click.stop="saveLanguageName(lang)"
                            >
                                <Check class="h-3 w-3" />
                                Salvar
                            </button>
                            <button
                                v-if="editingLanguageId === lang.id"
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800"
                                @click.stop="cancelEditLanguage"
                            >
                                <X class="h-3 w-3" />
                                Cancelar
                            </button>
                        </div>
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 lg:col-span-2">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <input
                        v-model="state.search"
                        type="text"
                        class="min-w-[260px] flex-1 rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                        :placeholder="t('settings.languages.search', 'Buscar chave de tradução...')"
                    />
                    <button
                        type="button"
                        class="rounded-xl border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-800"
                        @click="importMissing"
                    >
                        {{ t('settings.languages.import_missing', 'Importar chaves faltantes') }}
                    </button>
                    <Button type="button" :disabled="saving" @click="saveTranslations">
                        {{ saving ? 'Salvando...' : t('settings.languages.save', 'Salvar traduções') }}
                    </Button>
                </div>

                <div v-if="loading" class="text-sm text-zinc-500">Carregando...</div>
                <div v-else class="max-h-[520px] space-y-2 overflow-auto pr-1">
                    <div
                        v-for="key in filteredKeys"
                        :key="key"
                        class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700"
                    >
                        <p class="mb-1 text-xs font-medium text-zinc-500">{{ key }}</p>
                        <input
                            v-model="state.values[key]"
                            type="text"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                            :placeholder="key"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
