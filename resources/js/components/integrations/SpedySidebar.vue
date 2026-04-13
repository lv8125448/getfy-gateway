<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { X, Plus, Pencil, Trash2, ArrowLeft, Loader2 } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    spedy_integrations: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const { t } = useI18n();

const editingIntegration = ref(null);
const isCreating = ref(false);

const showingForm = computed(
    () => editingIntegration.value !== null || isCreating.value
);

const form = ref({
    name: '',
    api_key: '',
    environment: 'production',
    is_active: true,
    product_ids: [],
});
const saving = ref(false);
const deleting = ref(null);
const confirmingDeleteId = ref(null);
const errorMessage = ref(null);

watch(
    () => [props.open, props.spedy_integrations],
    () => {
        if (!props.open) {
            resetForm();
        }
    }
);

function resetForm() {
    editingIntegration.value = null;
    isCreating.value = false;
    confirmingDeleteId.value = null;
    form.value = {
        name: '',
        api_key: '',
        environment: 'production',
        is_active: true,
        product_ids: [],
    };
    errorMessage.value = null;
}

function startNew() {
    editingIntegration.value = null;
    isCreating.value = true;
    form.value = {
        name: '',
        api_key: '',
        environment: 'production',
        is_active: true,
        product_ids: [],
    };
    errorMessage.value = null;
}

function editIntegration(integration) {
    isCreating.value = false;
    editingIntegration.value = integration;
    form.value = {
        name: integration.name,
        api_key: integration.api_key ?? '',
        environment: integration.environment ?? 'production',
        is_active: integration.is_active ?? true,
        product_ids: [...(integration.product_ids || [])],
    };
    errorMessage.value = null;
}

function cancelEdit() {
    resetForm();
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
        errorMessage.value = t('integrations.error_name', 'Informe o nome da integração.');
        return;
    }
    if (isCreating.value && !form.value.api_key?.trim()) {
        errorMessage.value = t('integrations.error_api_key_create', 'Informe a chave de API ao criar uma integração.');
        return;
    }

    saving.value = true;
    try {
        const payload = {
            name: form.value.name.trim(),
            environment: form.value.environment,
            is_active: form.value.is_active,
            product_ids: form.value.product_ids,
        };
        if (form.value.api_key?.trim()) {
            payload.api_key = form.value.api_key.trim();
        }

        if (editingIntegration.value) {
            await axios.put(
                `/integracoes/spedy/${editingIntegration.value.id}`,
                payload
            );
        } else {
            if (!payload.api_key) {
                errorMessage.value = t('integrations.error_api_key', 'Informe a chave de API.');
                saving.value = false;
                return;
            }
            await axios.post('/integracoes/spedy', payload);
        }
        emit('saved');
        resetForm();
    } catch (err) {
        errorMessage.value =
            err.response?.data?.message || t('integrations.error_save', 'Erro ao salvar integração.');
    } finally {
        saving.value = false;
    }
}

function requestDelete(integration) {
    confirmingDeleteId.value = integration.id;
}

function cancelDelete() {
    confirmingDeleteId.value = null;
}

async function confirmRemove(integration) {
    if (!integration) return;
    deleting.value = integration.id;
    confirmingDeleteId.value = null;
    try {
        await axios.delete(`/integracoes/spedy/${integration.id}`);
        emit('saved');
        if (editingIntegration.value?.id === integration.id) {
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

function productSummary(integration) {
    if (!integration.product_ids?.length) return t('integrations.all_products', 'Todos os produtos');
    const n = integration.product_ids.length;
    return n === 1 ? '1 produto' : `${n} produtos`;
}

function environmentLabel(env) {
    return env === 'sandbox' ? t('integrations.spedy.sandbox', 'Sandbox (testes)') : t('integrations.spedy.production', 'Produção');
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
                        Spedy
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

                <div class="flex flex-1 flex-col overflow-y-auto p-5">
                    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                        Emissão automática de notas fiscais. Ao concluir uma venda, a Spedy recebe os dados e emite NF-e/NFS-e.
                    </p>

                    <!-- Lista de integrações -->
                    <template v-if="!showingForm">
                        <div class="mb-4 flex justify-end">
                            <Button variant="outline" size="sm" @click="startNew">
                                <Plus class="mr-2 h-4 w-4" />
                                {{ t('integrations.new', 'Nova integração') }}
                            </Button>
                        </div>

                        <ul v-if="spedy_integrations.length" class="space-y-2">
                            <li
                                v-for="i in spedy_integrations"
                                :key="i.id"
                                class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-zinc-900 dark:text-white">
                                            {{ i.name }}
                                        </span>
                                        <span
                                            v-if="i.is_active"
                                            class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300"
                                        >
                                            {{ t('common.active', 'Ativo') }}
                                        </span>
                                        <span
                                            v-else
                                            class="rounded bg-zinc-200 px-1.5 py-0.5 text-xs text-zinc-600 dark:bg-zinc-600 dark:text-zinc-300"
                                        >
                                            {{ t('common.inactive', 'Inativo') }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ i.configured ? t('integrations.key_configured', 'Chave configurada') : t('integrations.key_not_configured', 'Chave não configurada') }} · {{ environmentLabel(i.environment) }} · {{ productSummary(i) }}
                                    </p>
                                </div>
                                <div class="ml-2 flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                                        :aria-label="t('common.edit', 'Editar')"
                                        @click="editIntegration(i)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-zinc-500 hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                        :aria-label="t('common.delete', 'Excluir')"
                                        @click="requestDelete(i)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400"
                        >
                            {{ t('integrations.empty', 'Nenhuma integração configurada. Clique em \"Nova integração\" para começar.') }}
                        </p>
                    </template>

                    <!-- Formulário (novo ou editar) -->
                    <template v-else>
                        <div class="mb-4 flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-200/80 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                :aria-label="t('common.back', 'Voltar')"
                                @click="cancelEdit"
                            >
                                <ArrowLeft class="h-5 w-5" />
                            </button>
                            <span class="font-medium text-zinc-900 dark:text-white">
                                {{ isCreating ? t('integrations.new', 'Nova integração') : t('integrations.edit', 'Editar integração') }}
                            </span>
                        </div>

                        <p class="mb-4 rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            Para obter a chave de API: acesse o
                            <a
                                href="https://app.spedy.com.br"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline hover:no-underline"
                            >Backoffice Spedy</a>
                            (produção) ou
                            <a
                                href="https://sandbox-app.spedy.com.br"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline hover:no-underline"
                            >Sandbox</a>, vá em <strong>Perfil → Minha empresa → Credenciais da API</strong>.
                        </p>

                        <div class="space-y-4">
                            <div>
                                <label
                                    for="spedy-name"
                                    class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('integrations.integration_name', 'Nome da integração') }}
                                </label>
                                <input
                                    id="spedy-name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Ex: Notas fiscais principal"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500"
                                />
                            </div>

                            <div>
                                <label
                                    for="spedy-api-key"
                                    class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('integrations.api_key', 'Chave de API') }}
                                </label>
                                <input
                                    id="spedy-api-key"
                                    v-model="form.api_key"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="editingIntegration ? t('common.leave_blank_keep_current', 'Deixe em branco para manter a atual') : t('integrations.api_key_placeholder', 'Digite a chave')"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder:text-zinc-500 font-mono"
                                />
                            </div>

                            <div>
                                <label
                                    for="spedy-environment"
                                    class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300"
                                >
                                    {{ t('integrations.spedy.environment', 'Ambiente') }}
                                </label>
                                <select
                                    id="spedy-environment"
                                    v-model="form.environment"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                                >
                                    <option value="production">{{ t('integrations.spedy.production', 'Produção') }}</option>
                                    <option value="sandbox">{{ t('integrations.spedy.sandbox', 'Sandbox (testes)') }}</option>
                                </select>
                            </div>

                            <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div>
                                    <span class="block text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ t('integrations.active_integration', 'Integração ativa') }}
                                    </span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        Emitir notas fiscais ao concluir vendas
                                    </span>
                                </div>
                                <Toggle v-model="form.is_active" />
                            </div>

                            <div class="text-left">
                                <span class="mb-2 block text-left text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ t('integrations.assigned_products', 'Produtos atribuídos') }}
                                </span>
                                <p class="mb-2 text-left text-xs text-zinc-500 dark:text-zinc-400">
                                    Selecione os produtos para os quais esta integração emitirá notas. Deixe vazio para todos os produtos.
                                </p>
                                <div
                                    v-if="products.length"
                                    class="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-3 text-left dark:border-zinc-600 dark:bg-zinc-800"
                                >
                                    <label
                                        v-for="p in products"
                                        :key="p.id"
                                        class="flex cursor-pointer items-center justify-start gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                                    >
                                        <span class="shrink-0 w-fit">
                                            <Checkbox
                                                :model-value="isProductSelected(p.id)"
                                                @update:model-value="toggleProduct(p.id)"
                                            />
                                        </span>
                                        <span class="text-left text-sm text-zinc-900 dark:text-white">{{ p.name }}</span>
                                    </label>
                                </div>
                                <p v-else class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ t('products.empty', 'Nenhum produto cadastrado.') }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="errorMessage"
                            class="mt-4 rounded-lg bg-red-100 px-3 py-2 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300"
                        >
                            {{ errorMessage }}
                        </p>

                        <div class="mt-6 flex gap-2">
                            <Button
                                class="flex-1"
                                :disabled="saving"
                                @click="save"
                            >
                                <Loader2
                                    v-if="saving"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                {{ t('common.save', 'Salvar') }}
                            </Button>
                            <Button variant="outline" @click="cancelEdit">
                                {{ t('common.cancel', 'Cancelar') }}
                            </Button>
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </Teleport>

    <!-- Modal confirmar exclusão -->
    <Teleport to="body">
        <div
            v-if="confirmingDeleteId"
            class="fixed inset-0 z-[100001] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60"
                @click="cancelDelete"
            />
            <div
                class="relative max-w-sm rounded-xl bg-white p-5 shadow-xl dark:bg-zinc-900"
            >
                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ t('integrations.spedy.delete_confirm', 'Deseja realmente excluir esta integração Spedy? A emissão automática de notas será desativada para os produtos vinculados.') }}
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" @click="cancelDelete">
                        {{ t('common.cancel', 'Cancelar') }}
                    </Button>
                    <Button
                        variant="danger"
                        :disabled="deleting !== null"
                        @click="confirmRemove(spedy_integrations.find(i => i.id === confirmingDeleteId))"
                    >
                        <Loader2
                            v-if="deleting === confirmingDeleteId"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ t('common.delete', 'Excluir') }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
