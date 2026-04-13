<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { X, Plus, Pencil, Trash2, Send, ArrowLeft, Loader2, FileText } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    webhooks: { type: Array, default: () => [] },
    webhookEvents: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const { t } = useI18n();

const editingWebhook = ref(null);
const isCreating = ref(false);

const showingForm = computed(
    () => editingWebhook.value !== null || isCreating.value
);

const form = ref({
    name: '',
    url: '',
    bearer_token: '',
    events: [],
    is_active: true,
    product_ids: [],
});
const saving = ref(false);
const deleting = ref(null);
const confirmingDeleteId = ref(null);
const testing = ref(null);
const testMessage = ref(null);
const testSuccess = ref(null);
const errorMessage = ref(null);

const showTestModal = ref(false);
const testTargetWebhook = ref(null);
const selectedTestEvent = ref('');

const logsByWebhookId = ref({});
const loadingLogs = ref(null);
const expandedLogsWebhookId = ref(null);

const logDetailModal = ref(false);
const selectedLogDetail = ref(null);
const loadingLogDetail = ref(false);

const eventEntries = ref([]);

watch(
    () => props.webhookEvents,
    (events) => {
        eventEntries.value = Object.entries(events || {});
    },
    { immediate: true }
);

watch(
    () => [props.open, props.webhooks],
    () => {
        if (!props.open) {
            resetForm();
        }
    }
);

function resetForm() {
    editingWebhook.value = null;
    isCreating.value = false;
    confirmingDeleteId.value = null;
    form.value = {
        name: '',
        url: '',
        bearer_token: '',
        events: [],
        is_active: true,
        product_ids: [],
    };
    errorMessage.value = null;
    testMessage.value = null;
}

function startNew() {
    editingWebhook.value = null;
    isCreating.value = true;
    form.value = {
        name: '',
        url: '',
        bearer_token: '',
        events: [],
        is_active: true,
        product_ids: [],
    };
    errorMessage.value = null;
}

function editWebhook(w) {
    isCreating.value = false;
    editingWebhook.value = w;
    form.value = {
        name: w.name,
        url: w.url,
        bearer_token: '',
        events: [...(w.events || [])],
        is_active: w.is_active ?? true,
        product_ids: (w.products || []).map(p => p.id),
    };
    errorMessage.value = null;
}

function cancelEdit() {
    resetForm();
}

function toggleEvent(eventClass) {
    const idx = form.value.events.indexOf(eventClass);
    if (idx >= 0) {
        form.value.events.splice(idx, 1);
    } else {
        form.value.events.push(eventClass);
    }
}

function isEventSelected(eventClass) {
    return form.value.events.includes(eventClass);
}

function toggleProduct(productId) {
    const idx = form.value.product_ids.indexOf(productId);
    if (idx >= 0) {
        form.value.product_ids.splice(idx, 1);
    } else {
        form.value.product_ids.push(productId);
    }
}

function isProductSelected(productId) {
    return form.value.product_ids.includes(productId);
}

async function save() {
    errorMessage.value = null;
    if (!form.value.name?.trim()) {
        errorMessage.value = t('integrations.webhook.error_name', 'Informe o nome do webhook.');
        return;
    }
    if (!form.value.url?.trim()) {
        errorMessage.value = t('integrations.webhook.error_url', 'Informe a URL do webhook.');
        return;
    }
    if (form.value.events.length === 0) {
        errorMessage.value = t('integrations.webhook.error_event', 'Selecione pelo menos um evento.');
        return;
    }

    saving.value = true;
    try {
        const payload = {
            name: form.value.name.trim(),
            url: form.value.url.trim(),
            events: form.value.events,
            is_active: form.value.is_active,
            product_ids: form.value.product_ids,
        };
        if (form.value.bearer_token?.trim()) {
            payload.bearer_token = form.value.bearer_token.trim();
        }

        if (editingWebhook.value) {
            await axios.put(
                `/integracoes/webhooks/${editingWebhook.value.id}`,
                payload
            );
        } else {
            await axios.post('/integracoes/webhooks', payload);
        }
        emit('saved');
        resetForm(); // volta para a lista
    } catch (err) {
        errorMessage.value =
            err.response?.data?.message || t('integrations.error_save', 'Erro ao salvar webhook.');
    } finally {
        saving.value = false;
    }
}

function openTestModal(w) {
    testTargetWebhook.value = w;
    selectedTestEvent.value = eventEntries.value.length ? eventEntries.value[0][0] : '';
    testMessage.value = null;
    showTestModal.value = true;
}

function closeTestModal() {
    showTestModal.value = false;
    testTargetWebhook.value = null;
}

async function confirmTestSend() {
    if (!testTargetWebhook.value) return;
    const w = testTargetWebhook.value;
    testing.value = w.id;
    testMessage.value = null;
    closeTestModal();
    try {
        const { data } = await axios.post(`/integracoes/webhooks/${w.id}/test`, {
            event: selectedTestEvent.value || undefined,
        });
        testSuccess.value = data.success;
        testMessage.value = data.message || (data.success ? t('integrations.webhook.test_success', 'Evento enviado com sucesso!') : t('integrations.webhook.test_fail', 'Falha ao enviar.'));
        if (logsByWebhookId.value[w.id]) {
            await fetchLogs(w.id);
        }
    } catch (err) {
        testSuccess.value = false;
        testMessage.value =
            err.response?.data?.message || t('integrations.webhook.test_error', 'Erro ao disparar evento de teste.');
    } finally {
        testing.value = null;
    }
}

async function fetchLogs(webhookId) {
    loadingLogs.value = webhookId;
    try {
        const { data } = await axios.get(`/integracoes/webhooks/${webhookId}/logs`);
        logsByWebhookId.value[webhookId] = data.logs || [];
    } catch {
        logsByWebhookId.value[webhookId] = [];
    } finally {
        loadingLogs.value = null;
    }
}

function toggleLogs(w) {
    if (expandedLogsWebhookId.value === w.id) {
        expandedLogsWebhookId.value = null;
        return;
    }
    expandedLogsWebhookId.value = w.id;
    if (!logsByWebhookId.value[w.id]) {
        fetchLogs(w.id);
    }
}

function formatLogDate(iso) {
    if (!iso) return '–';
    const d = new Date(iso);
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function openLogDetail(webhookId, logId) {
    loadingLogDetail.value = true;
    selectedLogDetail.value = null;
    logDetailModal.value = true;
    try {
        const { data } = await axios.get(
            `/integracoes/webhooks/${webhookId}/logs/${logId}`
        );
        selectedLogDetail.value = data.log;
    } catch {
        selectedLogDetail.value = null;
    } finally {
        loadingLogDetail.value = false;
    }
}

function closeLogDetail() {
    logDetailModal.value = false;
    selectedLogDetail.value = null;
}

function formatPayload(obj) {
    if (obj == null) return '–';
    try {
        if (typeof obj === 'string') {
            const trimmed = obj.trim();
            if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
                return JSON.stringify(JSON.parse(obj), null, 2);
            }
            return obj;
        }
        return JSON.stringify(obj, null, 2);
    } catch {
        return String(obj);
    }
}

function copyToClipboard(text, label) {
    if (text == null) return;
    const s = typeof text === 'string' ? text : JSON.stringify(text, null, 2);
    navigator.clipboard.writeText(s).then(() => {
        // poderia usar um toast; por simplicidade não adicionamos
    });
}

function requestDelete(w) {
    confirmingDeleteId.value = w.id;
}

function cancelDelete() {
    confirmingDeleteId.value = null;
}

async function confirmRemoveWebhook(w) {
    deleting.value = w.id;
    confirmingDeleteId.value = null;
    try {
        await axios.delete(`/integracoes/webhooks/${w.id}`);
        emit('saved');
        if (editingWebhook.value?.id === w.id) {
            resetForm();
        }
    } catch (err) {
        errorMessage.value =
            err.response?.data?.message || t('integrations.error_delete', 'Erro ao excluir integração.');
    } finally {
        deleting.value = null;
    }
}

function close() {
    emit('close');
}

function truncateUrl(url, max = 40) {
    if (!url) return '';
    if (url.length <= max) return url;
    return url.slice(0, max) + '…';
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
                class="relative flex h-full w-full max-w-lg flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900"
            >
                <div
                    class="flex items-center justify-between rounded-tl-2xl bg-zinc-50/80 px-5 py-4 dark:bg-zinc-800/50"
                >
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ t('integrations.webhook.title', 'Webhooks') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-200/80 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                        :aria-label="t('common.close', 'Fechar')"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex flex-1 flex-col overflow-y-auto">
                    <!-- Lista de webhooks (visível quando não está criando/editando) -->
                    <template v-if="!showingForm">
                        <div class="p-4">
                            <Button
                                variant="outline"
                                size="sm"
                                class="w-full"
                                @click="startNew"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                {{ t('integrations.webhook.new', 'Novo webhook') }}
                            </Button>
                        </div>

                        <div class="flex-1 px-4 pb-6">
                            <h3 class="mb-3 text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                {{ t('integrations.webhook.configured', 'Webhooks configurados') }}
                            </h3>
                            <ul v-if="webhooks.length > 0" class="space-y-3">
                                <li
                                    v-for="w in webhooks"
                                    :key="w.id"
                                    class="rounded-2xl bg-zinc-50 shadow-sm transition-shadow hover:shadow dark:bg-zinc-800/60 dark:hover:shadow-zinc-900/50"
                                >
                                    <div class="flex items-center justify-between gap-2 px-4 py-3">
                                        <button
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="editWebhook(w)"
                                        >
                                            <div class="font-medium text-zinc-900 dark:text-white">
                                                {{ w.name }}
                                            </div>
                                            <div
                                                class="truncate text-xs text-zinc-500 dark:text-zinc-400"
                                                :title="w.url"
                                            >
                                                {{ truncateUrl(w.url) }}
                                            </div>
                                            <div
                                                class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400"
                                            >
                                                {{ (w.events || []).length }} {{ t('integrations.webhook.events_count', 'evento(s)') }}
                                                <span v-if="(w.products || []).length > 0" class="ml-1">
                                                    • {{ (w.products || []).length }} {{ t('integrations.webhook.products_count', 'produto(s)') }}
                                                </span>
                                                <span v-else class="ml-1 text-zinc-400">
                                                    • {{ t('integrations.all_products', 'Todos os produtos') }}
                                                </span>
                                                <span
                                                    v-if="w.has_bearer_token"
                                                    class="ml-1 rounded-md bg-zinc-200 px-1.5 py-0.5 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200"
                                                >
                                                    Token
                                                </span>
                                                <span
                                                    v-if="!w.is_active"
                                                    class="ml-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                                >
                                                    {{ t('common.inactive', 'Inativo') }}
                                                </span>
                                            </div>
                                        </button>
                                        <div class="flex shrink-0 items-center gap-0.5">
                                            <template v-if="confirmingDeleteId === w.id">
                                                <span class="mr-1 text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ t('common.delete_question', 'Excluir?') }}</span>
                                                <button
                                                    type="button"
                                                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-200/80 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                                    @click.stop="cancelDelete()"
                                                >
                                                    {{ t('common.cancel', 'Cancelar') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="flex items-center gap-1 rounded-lg bg-red-100 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300 dark:hover:bg-red-900/60"
                                                    :disabled="deleting === w.id"
                                                    @click.stop="confirmRemoveWebhook(w)"
                                                >
                                                    <Loader2 v-if="deleting === w.id" class="h-3 w-3 animate-spin" />
                                                    <Trash2 v-else class="h-3 w-3" />
                                                    {{ deleting === w.id ? t('common.deleting', 'Excluindo...') : t('common.delete', 'Excluir') }}
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button
                                                    type="button"
                                                    class="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-500 hover:bg-emerald-100 hover:text-emerald-600 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-400"
                                                    :title="t('integrations.webhook.trigger_test', 'Disparar evento de teste')"
                                                    :disabled="testing === w.id"
                                                    @click.stop="openTestModal(w)"
                                                >
                                                    <Loader2
                                                        v-if="testing === w.id"
                                                        class="h-3.5 w-3.5 animate-spin"
                                                    />
                                                    <Send v-else class="h-3.5 w-3.5" />
                                                    {{ testing === w.id ? t('common.sending', 'Enviando...') : t('common.test', 'Testar') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-200/80 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                                    :title="t('team.logs', 'Logs')"
                                                    :class="{ 'bg-zinc-200/80 dark:bg-zinc-700': expandedLogsWebhookId === w.id }"
                                                    @click.stop="toggleLogs(w)"
                                                >
                                                    <FileText class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-200/80 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                                    :title="t('common.edit', 'Editar')"
                                                    @click.stop="editWebhook(w)"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-2 text-zinc-500 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                                    :title="t('common.delete', 'Excluir')"
                                                    :disabled="deleting === w.id"
                                                    @click.stop="requestDelete(w)"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <div
                                        v-if="expandedLogsWebhookId === w.id"
                                        class="bg-white/60 px-4 py-3 dark:bg-zinc-900/40"
                                    >
                                        <div class="mb-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                            {{ t('integrations.webhook.last_deliveries', 'Últimos envios') }}
                                        </div>
                                        <div
                                            v-if="loadingLogs === w.id"
                                            class="flex items-center gap-2 py-4 text-sm text-zinc-500"
                                        >
                                            <Loader2 class="h-4 w-4 animate-spin" />
                                            {{ t('common.loading', 'Carregando...') }}
                                        </div>
                                        <ul
                                            v-else-if="(logsByWebhookId[w.id] || []).length > 0"
                                            class="max-h-48 space-y-1.5 overflow-y-auto text-xs"
                                        >
                                            <li
                                                v-for="log in logsByWebhookId[w.id]"
                                                :key="log.id"
                                                class="flex cursor-pointer flex-wrap items-center justify-between gap-x-2 gap-y-0.5 rounded-xl bg-white/80 px-3 py-2 transition hover:bg-zinc-100 dark:bg-zinc-800/80 dark:hover:bg-zinc-700/80"
                                                role="button"
                                                tabindex="0"
                                                @click="openLogDetail(w.id, log.id)"
                                                @keydown.enter="openLogDetail(w.id, log.id)"
                                            >
                                                <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                                    {{ log.event_label || log.event }}
                                                </span>
                                                <span
                                                    :class="[
                                                        'rounded px-1.5 py-0.5 text-[10px] font-medium',
                                                        log.success
                                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                                    ]"
                                                >
                                                    {{ log.success ? (log.response_status || 'OK') : (log.response_status || t('common.error', 'Erro')) }}
                                                </span>
                                                <span class="w-full text-zinc-500 dark:text-zinc-400">
                                                    {{ formatLogDate(log.created_at) }}
                                                    <span v-if="log.source === 'test'" class="ml-1">(teste)</span>
                                                </span>
                                                <p
                                                    v-if="log.error_message"
                                                    class="w-full truncate text-red-600 dark:text-red-400"
                                                    :title="log.error_message"
                                                >
                                                    {{ log.error_message }}
                                                </p>
                                            </li>
                                        </ul>
                                        <p
                                            v-else
                                            class="py-3 text-center text-xs text-zinc-500 dark:text-zinc-400"
                                        >
                                            {{ t('integrations.webhook.no_deliveries', 'Nenhum envio registrado.') }}
                                        </p>
                                    </div>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="rounded-2xl bg-zinc-50 py-8 text-center text-sm text-zinc-500 dark:bg-zinc-800/40 dark:text-zinc-400"
                            >
                                {{ t('integrations.webhook.empty', 'Nenhum webhook configurado. Clique em \"Novo webhook\" para criar.') }}
                            </p>
                            <p
                                v-if="testMessage"
                                :class="[
                                    'mt-3 rounded-lg px-3 py-2 text-sm',
                                    testSuccess
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                ]"
                            >
                                {{ testMessage }}
                            </p>
                        </div>
                    </template>

                    <!-- Formulário (só ao criar ou editar) -->
                    <div
                        v-else
                        class="flex flex-1 flex-col bg-zinc-50/50 p-4 dark:bg-zinc-800/30"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                :title="t('common.back_to_list', 'Voltar à lista')"
                                @click="cancelEdit"
                            >
                                <ArrowLeft class="h-5 w-5" />
                            </button>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                                {{ editingWebhook ? t('integrations.webhook.edit', 'Editar webhook') : t('integrations.webhook.new', 'Novo webhook') }}
                            </h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('common.name', 'Nome') }}
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Ex: Minha integração"
                                    class="block w-full rounded-xl bg-white px-4 py-2.5 text-zinc-900 shadow-sm ring-1 ring-zinc-200 placeholder-zinc-400 transition focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:ring-offset-0 dark:bg-zinc-800 dark:ring-zinc-600 dark:text-white dark:placeholder-zinc-500"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    URL
                                </label>
                                <input
                                    v-model="form.url"
                                    type="url"
                                    placeholder="https://seu-endpoint.com/webhook"
                                    class="block w-full rounded-xl bg-white px-4 py-2.5 text-zinc-900 shadow-sm ring-1 ring-zinc-200 placeholder-zinc-400 transition focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:ring-offset-0 dark:bg-zinc-800 dark:ring-zinc-600 dark:text-white dark:placeholder-zinc-500"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('integrations.webhook.bearer_token', 'Bearer token') }}
                                    <span class="font-normal text-zinc-500"
                                        >({{ t('common.optional', 'opcional') }})</span
                                    >
                                </label>
                                <p
                                    class="mb-1.5 text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    {{ t('integrations.webhook.token_security_hint', 'Por segurança, o valor do token salvo não é exibido neste campo.') }}
                                </p>
                                <input
                                    v-model="form.bearer_token"
                                    type="password"
                                    :placeholder="
                                        editingWebhook ? t('common.leave_blank_keep', 'Deixe em branco para manter') : t('integrations.webhook.auth_token', 'Token de autenticação')
                                    "
                                    autocomplete="new-password"
                                    class="block w-full rounded-xl bg-white px-4 py-2.5 text-zinc-900 shadow-sm ring-1 ring-zinc-200 placeholder-zinc-400 transition focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 focus:ring-offset-0 dark:bg-zinc-800 dark:ring-zinc-600 dark:text-white dark:placeholder-zinc-500"
                                />
                                <p
                                    v-if="editingWebhook?.has_bearer_token && !form.bearer_token"
                                    class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    {{ t('integrations.webhook.token_already_saved', 'Token já está salvo. Deixe em branco para manter.') }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('integrations.webhook.events', 'Eventos') }}
                                </label>
                                <div
                                    class="max-h-40 space-y-2 overflow-y-auto rounded-xl bg-white p-3 shadow-sm ring-1 ring-zinc-200/80 dark:ring-zinc-600 dark:bg-zinc-800/50"
                                >
                                    <Checkbox
                                        v-for="[eventClass, label] in eventEntries"
                                        :key="eventClass"
                                        :model-value="isEventSelected(eventClass)"
                                        :label="label"
                                        class="block"
                                        @update:model-value="
                                            toggleEvent(eventClass)
                                        "
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('sidebar.products', 'Produtos') }}
                                    <span class="font-normal text-zinc-500">
                                        ({{ t('integrations.optional_leave_all', 'opcional - deixe vazio para todos') }})
                                    </span>
                                </label>
                                <div
                                    class="max-h-40 space-y-2 overflow-y-auto rounded-xl bg-white p-3 shadow-sm ring-1 ring-zinc-200/80 dark:ring-zinc-600 dark:bg-zinc-800/50"
                                >
                                    <template v-if="products.length > 0">
                                        <Checkbox
                                            v-for="product in products"
                                            :key="product.id"
                                            :model-value="isProductSelected(product.id)"
                                            :label="product.name"
                                            class="block"
                                            @update:model-value="toggleProduct(product.id)"
                                        />
                                    </template>
                                    <p
                                        v-else
                                        class="py-2 text-center text-xs text-zinc-500 dark:text-zinc-400"
                                    >
                                        {{ t('products.empty', 'Nenhum produto cadastrado') }}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <Toggle
                                    v-model="form.is_active"
                                    :label="t('common.active', 'Ativo')"
                                />
                            </div>
                        </div>

                        <p
                            v-if="errorMessage"
                            class="mt-4 rounded-lg bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300"
                        >
                            {{ errorMessage }}
                        </p>

                        <div class="mt-4 flex gap-2">
                            <Button
                                variant="outline"
                                :disabled="saving"
                                @click="cancelEdit"
                            >
                                {{ t('common.cancel', 'Cancelar') }}
                            </Button>
                            <Button :disabled="saving" @click="save">
                                {{ saving ? t('common.saving', 'Salvando...') : t('common.save', 'Salvar') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Modal: escolher evento para teste -->
                <div
                    v-if="showTestModal && testTargetWebhook"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-zinc-900/40 p-4 dark:bg-zinc-950/60"
                    @click.self="closeTestModal"
                >
                    <div
                        class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-zinc-800"
                        role="dialog"
                        aria-labelledby="test-modal-title"
                    >
                        <h3 id="test-modal-title" class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ t('integrations.webhook.send_test_event', 'Enviar evento de teste') }}
                        </h3>
                        <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ testTargetWebhook.name }}
                        </p>
                        <div class="mb-4">
                            <label class="mb-1.5 block text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                {{ t('integrations.webhook.event', 'Evento') }}
                            </label>
                            <select
                                v-model="selectedTestEvent"
                                class="block w-full rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-900 ring-1 ring-zinc-200/80 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/30 dark:bg-zinc-700 dark:ring-zinc-600 dark:text-white"
                            >
                                <option
                                    v-for="[eventClass, label] in eventEntries"
                                    :key="eventClass"
                                    :value="eventClass"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" size="sm" @click="closeTestModal">
                                {{ t('common.cancel', 'Cancelar') }}
                            </Button>
                            <Button size="sm" @click="confirmTestSend">
                                {{ t('common.send', 'Enviar') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Modal: detalhe do log (payload, resposta, etc.) -->
                <div
                    v-if="logDetailModal"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-zinc-900/40 p-4 dark:bg-zinc-950/60"
                    @click.self="closeLogDetail"
                >
                    <div
                        class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-2xl dark:bg-zinc-800"
                        role="dialog"
                        aria-labelledby="log-detail-title"
                    >
                        <div class="flex items-center justify-between bg-zinc-50/80 px-5 py-3 dark:bg-zinc-800/80">
                            <h3 id="log-detail-title" class="text-sm font-semibold text-zinc-900 dark:text-white">
                                Detalhe do envio
                            </h3>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                aria-label="Fechar"
                                @click="closeLogDetail"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4">
                            <div v-if="loadingLogDetail" class="flex items-center justify-center py-12">
                                <Loader2 class="h-8 w-8 animate-spin text-zinc-400" />
                            </div>
                            <template v-else-if="selectedLogDetail">
                                <div class="mb-4 flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                        {{ selectedLogDetail.event_label || selectedLogDetail.event }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-xs font-medium',
                                            selectedLogDetail.success
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                        ]"
                                    >
                                        {{ selectedLogDetail.success ? 'Sucesso' : 'Falha' }}
                                        <span v-if="selectedLogDetail.response_status != null">
                                            (HTTP {{ selectedLogDetail.response_status }})
                                        </span>
                                    </span>
                                    <span v-if="selectedLogDetail.source === 'test'" class="rounded bg-zinc-200 px-2 py-0.5 text-xs dark:bg-zinc-600">
                                        Teste manual
                                    </span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ formatLogDate(selectedLogDetail.created_at) }}
                                    </span>
                                </div>
                                <p
                                    v-if="selectedLogDetail.error_message"
                                    class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300"
                                >
                                    {{ selectedLogDetail.error_message }}
                                </p>
                                <div class="space-y-4">
                                    <div>
                                        <div class="mb-1 flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                Payload enviado (request)
                                            </span>
                                            <button
                                                type="button"
                                                class="rounded px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-300"
                                                @click="copyToClipboard(selectedLogDetail.request_payload, 'Payload')"
                                            >
                                                Copiar
                                            </button>
                                        </div>
                                        <pre class="max-h-64 overflow-auto rounded-xl bg-zinc-50 p-4 text-xs leading-relaxed text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ formatPayload(selectedLogDetail.request_payload) }}</pre>
                                    </div>
                                    <div>
                                        <div class="mb-1 flex items-center justify-between">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                Resposta do servidor (response)
                                            </span>
                                            <button
                                                type="button"
                                                class="rounded px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-300"
                                                @click="copyToClipboard(selectedLogDetail.response_body, 'Resposta')"
                                            >
                                                Copiar
                                            </button>
                                        </div>
                                        <p v-if="selectedLogDetail.response_status != null" class="mb-1 text-xs text-zinc-600 dark:text-zinc-400">
                                            Status: {{ selectedLogDetail.response_status }}
                                        </p>
                                        <pre class="max-h-64 overflow-auto rounded-xl bg-zinc-50 p-4 text-xs leading-relaxed text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">{{ formatPayload(selectedLogDetail.response_body) }}</pre>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="bg-zinc-50/80 px-5 py-3 dark:bg-zinc-800/80">
                            <Button variant="outline" size="sm" class="w-full sm:w-auto" @click="closeLogDetail">
                                Fechar
                            </Button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
