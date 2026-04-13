<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import { X, ExternalLink, Copy, Check } from 'lucide-vue-next';
import PixInOutBadges from '@/components/settings/PixInOutBadges.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    gatewaySlug: { type: String, default: null },
    /** Base path sem barra final, ex.: /plataforma/financeiro/gateways */
    apiBasePath: { type: String, default: '/plataforma/configuracoes/gateways' },
});

const apiBase = computed(() => String(props.apiBasePath || '/plataforma/configuracoes/gateways').replace(/\/$/, ''));

const emit = defineEmits(['close', 'saved']);

function getCsrfToken() {
    return typeof document !== 'undefined'
        ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            document.querySelector('meta[name="X-XSRF-TOKEN"]')?.getAttribute('content') ||
            '')
        : '';
}

const gateway = ref(null);
const loading = ref(false);
const saving = ref(false);
const testing = ref(false);
const testMessage = ref(null);
const testSuccess = ref(null);
const credentialValues = ref({});
const certificateFile = ref(null);
const webhookCopied = ref(false);
const disconnecting = ref(false);

async function copyWebhookUrl() {
    const url = gateway.value?.webhook_url;
    if (!url) return;
    try {
        await navigator.clipboard.writeText(url);
        webhookCopied.value = true;
        setTimeout(() => { webhookCopied.value = false; }, 2000);
    } catch {
        webhookCopied.value = false;
    }
}

watch(
    () => [props.open, props.gatewaySlug],
    async ([open, slug]) => {
        if (open && slug) {
            loading.value = true;
            testMessage.value = null;
            webhookCopied.value = false;
            credentialValues.value = {};
            try {
                const { data } = await axios.get(`${apiBase.value}/${encodeURIComponent(slug)}`, {
                    params: { t: Date.now() },
                });
                gateway.value = data;
                const keys = data.credential_keys || [];
                const saved = data.credential_values || {};
                const initial = {};
                for (const k of keys) {
                    if ((k.type || 'text') === 'file') continue;
                    const key = k.key;
                    if (key == null) continue;
                    const v = saved[key];
                    if (k.type === 'boolean') {
                        initial[key] = v === true || v === '1' || v === 'true';
                    } else {
                        initial[key] = v != null && v !== '' ? String(v) : '';
                    }
                }
                credentialValues.value = { ...initial };
                certificateFile.value = null;
            } catch {
                gateway.value = null;
            } finally {
                loading.value = false;
            }
        } else {
            gateway.value = null;
        }
    },
    { immediate: true }
);

const inputClass =
    'block w-full rounded-xl border-2 border-zinc-200 bg-white px-4 py-2.5 text-zinc-900 placeholder-zinc-400 transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500';

function buildTestPayload() {
    const keys = gateway.value?.credential_keys || [];
    const payload = {};
    for (const k of keys) {
        if ((k.type || 'text') === 'file') continue;
        const v = credentialValues.value[k.key];
        if (k.type === 'boolean') {
            payload[k.key] = v === true || v === '1' || v === 'true';
        } else if (v != null && String(v).trim() !== '') {
            payload[k.key] = String(v).trim();
        }
    }
    return payload;
}

async function testConnection() {
    if (!gateway.value?.slug) return;
    const keys = gateway.value.credential_keys || [];
    const certificateKey = gateway.value.certificate_key;
    for (const k of keys) {
        if (k.key === certificateKey) continue;
        if ((k.type || 'text') === 'boolean') continue;
        if (k.optional) continue;
        const v = credentialValues.value[k.key];
        if (v == null || String(v).trim() === '') {
            testMessage.value = 'Preencha as credenciais obrigatórias para testar.';
            testSuccess.value = false;
            return;
        }
    }
    if (certificateKey && !gateway.value.certificate_configured && !certificateFile.value) {
        testMessage.value = 'Envie e salve o certificado P12 antes de testar.';
        testSuccess.value = false;
        return;
    }
    const payload = buildTestPayload();
    testing.value = true;
    testMessage.value = null;
    try {
        const { data } = await axios.post(
            `${apiBase.value}/${encodeURIComponent(gateway.value.slug)}/test`,
            payload,
            { headers: { 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' } }
        );
        testSuccess.value = data.success;
        testMessage.value = data.message || (data.success ? 'Conexão OK.' : 'Falha.');
    } catch (err) {
        testSuccess.value = false;
        testMessage.value =
            err.response?.data?.message || 'Erro ao testar conexão.';
    } finally {
        testing.value = false;
    }
}

async function save() {
    if (!gateway.value?.slug) return;
    saving.value = true;
    testMessage.value = null;
    try {
        const keys = gateway.value.credential_keys || [];
        const certificateKey = gateway.value.certificate_key;

        // 1) Salva sempre as credenciais (sem arquivo) em JSON
        const payload = {};
        for (const k of keys) {
            if (k.key === certificateKey) continue;
            const v = credentialValues.value[k.key];
            if (k.type === 'boolean') {
                payload[k.key] = v === true || v === '1' || v === 'true';
            } else {
                payload[k.key] = v != null ? String(v).trim() : '';
            }
        }
        const { data } = await axios.put(
            `${apiBase.value}/${encodeURIComponent(gateway.value.slug)}`,
            payload,
            { headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Content-Type': 'application/json', Accept: 'application/json' } }
        );

        // 2) Se tiver certificado, envia em chamada separada
        if (certificateKey && certificateFile.value) {
            const form = new FormData();
            form.append(certificateKey, certificateFile.value);
            await axios.post(
                `${apiBase.value}/${encodeURIComponent(gateway.value.slug)}/certificate`,
                form,
                { headers: { 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' } }
            );
        }

        certificateFile.value = null;
        testSuccess.value = true;
        testMessage.value = data?.message || 'Credenciais salvas.';
        emit('saved');
        setTimeout(() => {
            emit('close');
        }, 1500);
    } catch (err) {
        testSuccess.value = false;
        const res = err.response?.data;
        let msg = res?.message || 'Erro ao salvar.';
        if (res?.errors && typeof res.errors === 'object') {
            const parts = Object.values(res.errors).flat().filter(Boolean);
            if (parts.length) msg = parts.join(' ');
        }
        testMessage.value = msg;
    } finally {
        saving.value = false;
    }
}

function close() {
    emit('close');
}

async function disconnectOAuth() {
    const url = gateway.value?.oauth_disconnect_url;
    if (!url) return;
    disconnecting.value = true;
    testMessage.value = null;
    try {
        await axios.post(
            url,
            {},
            { headers: { 'X-XSRF-TOKEN': getCsrfToken(), Accept: 'application/json' } }
        );
        testSuccess.value = true;
        testMessage.value = 'Conta desconectada.';
        emit('saved');
        const slug = gateway.value?.slug;
        if (slug) {
            const { data } = await axios.get(`${apiBase.value}/${encodeURIComponent(slug)}`, {
                params: { t: Date.now() },
            });
            gateway.value = data;
        }
    } catch (err) {
        testSuccess.value = false;
        testMessage.value =
            err.response?.data?.message || 'Não foi possível desconectar.';
    } finally {
        disconnecting.value = false;
    }
}

const hasManualCredentialFields = computed(() => {
    const keys = gateway.value?.credential_keys || [];
    return keys.length > 0 || !!gateway.value?.certificate_key;
});

const canTestConnection = computed(() => {
    if (!gateway.value) return false;
    if (gateway.value.uses_oauth && !gateway.value.oauth_connected) {
        return false;
    }
    return true;
});
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
                class="relative flex h-full w-full max-w-md flex-col rounded-l-2xl border-l border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
            >
                <div
                    class="flex items-center justify-between gap-2 rounded-tl-2xl border-b border-zinc-200 px-4 py-4 dark:border-zinc-700"
                >
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                            {{ gateway?.name || 'Gateway' }}
                        </h2>
                        <PixInOutBadges :slug="gateway?.slug" />
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="loading" class="flex flex-1 items-center justify-center p-8">
                    <p class="text-sm text-zinc-500">Carregando...</p>
                </div>

                <div v-else-if="gateway" class="flex flex-1 flex-col overflow-y-auto p-4">
                    <!-- Criar conta -->
                    <a
                        v-if="gateway.signup_url"
                        :href="gateway.signup_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mb-6 flex items-center gap-2 rounded-xl border-2 border-[var(--color-primary)] bg-[var(--color-primary)]/10 px-4 py-3 text-sm font-medium text-[var(--color-primary)] transition hover:bg-[var(--color-primary)]/20"
                    >
                        <ExternalLink class="h-4 w-4 shrink-0" />
                        Criar conta no {{ gateway.name }}
                    </a>

                    <!-- URL do webhook para configurar no painel do gateway -->
                    <div
                        v-if="gateway.webhook_url"
                        class="mb-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-800/50"
                    >
                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            URL do webhook
                        </h3>
                        <p class="mb-2 text-xs text-zinc-600 dark:text-zinc-400">
                            Configure esta URL no painel do {{ gateway.name }} (notificações de pagamento).
                        </p>
                        <div class="flex gap-2">
                            <input
                                :value="gateway.webhook_url"
                                type="text"
                                readonly
                                class="flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                            />
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                @click="copyWebhookUrl"
                            >
                                <Check v-if="webhookCopied" class="h-4 w-4 text-emerald-600" />
                                <Copy v-else class="h-4 w-4" />
                                {{ webhookCopied ? 'Copiado!' : 'Copiar' }}
                            </button>
                        </div>
                    </div>

                    <h3
                        v-if="hasManualCredentialFields"
                        class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"
                    >
                        Credenciais
                    </h3>
                    <div v-if="hasManualCredentialFields" class="space-y-4">
                        <div
                            v-for="field in (gateway.credential_keys || [])"
                            :key="field.key"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                            >
                                {{ field.label }}
                            </label>
                            <template v-if="field.type === 'file'">
                                <input
                                    type="file"
                                    accept=".p12"
                                    class="block w-full text-sm text-zinc-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[var(--color-primary)] file:px-4 file:py-2 file:text-white file:transition dark:text-zinc-400"
                                    @change="certificateFile = $event.target.files?.[0] || null"
                                />
                                <p
                                    v-if="gateway.certificate_configured && !certificateFile"
                                    class="mt-1 text-xs text-zinc-500 dark:text-zinc-400"
                                >
                                    <span v-if="gateway.certificate_filename" class="font-medium text-zinc-700 dark:text-zinc-300">Em uso: {{ gateway.certificate_filename }}</span>
                                    <template v-else>Certificado já enviado.</template>
                                    <span> Envie novamente para substituir.</span>
                                </p>
                            </template>
                            <template v-else-if="field.type === 'boolean'">
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input
                                        v-model="credentialValues[field.key]"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-zinc-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)] dark:border-zinc-600"
                                    />
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Sim (somente para testes)</span>
                                </label>
                            </template>
                            <input
                                v-else
                                v-model="credentialValues[field.key]"
                                :type="field.type === 'password' ? 'password' : 'text'"
                                :placeholder="field.label"
                                :class="inputClass"
                                autocomplete="off"
                            />
                        </div>
                    </div>

                    <div
                        v-if="gateway.uses_oauth"
                        class="mb-6 mt-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-600 dark:bg-zinc-800/50"
                    >
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Conectar via OAuth
                        </h3>
                        <div class="mb-4 flex flex-col gap-2">
                            <Button
                                v-if="gateway.oauth_start_url && !gateway.oauth_connected"
                                as="a"
                                :href="gateway.oauth_start_url"
                                variant="primary"
                                class="w-full justify-center text-center no-underline sm:w-full"
                            >
                                <ExternalLink class="h-4 w-4 shrink-0" aria-hidden="true" />
                                Conectar
                            </Button>
                            <p
                                v-if="gateway.oauth_start_url && !gateway.oauth_connected"
                                class="text-center text-[11px] text-zinc-500 dark:text-zinc-400"
                            >
                                Abre o fluxo de autorização do gateway e, após o consentimento, salva o token no Getfy.
                            </p>
                            <Button
                                v-if="gateway.oauth_disconnect_url && gateway.oauth_connected"
                                type="button"
                                variant="outline"
                                class="w-full justify-center sm:w-auto"
                                :disabled="disconnecting"
                                @click="disconnectOAuth"
                            >
                                {{ disconnecting ? 'Desconectando...' : 'Desconectar' }}
                            </Button>
                        </div>
                        <p
                            v-if="!gateway.oauth_client_configured"
                            class="mb-3 text-xs text-amber-700 dark:text-amber-300"
                        >
                            A identificação do aplicativo OAuth ainda não está configurada neste servidor (variáveis de ambiente ou registro do gateway).
                        </p>
                        <template v-else>
                            <p
                                v-if="gateway.oauth_callback_url && !gateway.oauth_connected"
                                class="mb-3 text-xs text-zinc-600 dark:text-zinc-400"
                            >
                                Na primeira conexão, cadastre a URL de callback no painel do integrador, se solicitado.
                            </p>
                            <p
                                v-if="gateway.oauth_callback_url"
                                class="mb-1 text-xs font-medium text-zinc-600 dark:text-zinc-400"
                            >
                                URL de redirecionamento (callback)
                            </p>
                            <p
                                v-if="gateway.oauth_callback_url"
                                class="mb-3 break-all rounded-lg bg-white px-2 py-1.5 font-mono text-[11px] text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200"
                            >
                                {{ gateway.oauth_callback_url }}
                            </p>
                        </template>
                        <p
                            v-if="gateway.oauth_connected"
                            class="text-xs text-emerald-700 dark:text-emerald-300"
                        >
                            Conta autorizada. Teste a conexão abaixo ou desconecte.
                        </p>
                    </div>

                    <p
                        v-if="testMessage"
                        :class="[
                            'mt-4 rounded-lg px-3 py-2 text-sm',
                            testSuccess
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                        ]"
                    >
                        {{ testMessage }}
                    </p>

                    <div class="mt-6 flex flex-col gap-2">
                        <Button
                            variant="outline"
                            :disabled="testing || !canTestConnection"
                            @click="testConnection"
                        >
                            {{ testing ? 'Testando...' : 'Testar conexão' }}
                        </Button>
                        <Button
                            v-if="hasManualCredentialFields"
                            :disabled="saving"
                            @click="save"
                        >
                            {{ saving ? 'Salvando...' : 'Salvar' }}
                        </Button>
                    </div>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
