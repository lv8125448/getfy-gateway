<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import Button from '@/components/ui/Button.vue';
import { UserPlus, Trash2, Pencil, X, Eye } from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const props = defineProps({
    users: { type: Array, default: () => [] },
    gateways: { type: Array, default: () => [] },
    platform_gateway_order: {
        type: Object,
        default: () => ({ pix: [], card: [], boleto: [], pix_auto: [] }),
    },
});

const page = usePage();

const editUser = ref(null);
const deletingId = ref(null);

function defaultFeeOverrides() {
    return {
        pix: { percent: '', fixed: '' },
        card: { percent: '', fixed: '' },
        boleto: { percent: '', fixed: '' },
        withdrawal: { percent: '', fixed: '' },
    };
}

function mergeFeeOverrides(raw) {
    const d = defaultFeeOverrides();
    if (!raw || typeof raw !== 'object') return d;
    for (const k of ['pix', 'card', 'boleto', 'withdrawal']) {
        if (raw[k] && typeof raw[k] === 'object') {
            if (raw[k].percent != null && raw[k].percent !== '') d[k].percent = raw[k].percent;
            if (raw[k].fixed != null && raw[k].fixed !== '') d[k].fixed = raw[k].fixed;
        }
    }
    return d;
}

function defaultSettlementOverrides() {
    return {
        pix: { days_to_available: '', reserve_percent: '', reserve_hold_days: '' },
        card: { days_to_available: '', reserve_percent: '', reserve_hold_days: '' },
        boleto: { days_to_available: '', reserve_percent: '', reserve_hold_days: '' },
    };
}

function mergeSettlementOverrides(raw) {
    const d = defaultSettlementOverrides();
    if (!raw || typeof raw !== 'object') return d;
    for (const k of ['pix', 'card', 'boleto']) {
        if (raw[k] && typeof raw[k] === 'object') {
            if (raw[k].days_to_available != null && raw[k].days_to_available !== '') {
                d[k].days_to_available = raw[k].days_to_available;
            }
            if (raw[k].reserve_percent != null && raw[k].reserve_percent !== '') {
                d[k].reserve_percent = raw[k].reserve_percent;
            }
            if (raw[k].reserve_hold_days != null && raw[k].reserve_hold_days !== '') {
                d[k].reserve_hold_days = raw[k].reserve_hold_days;
            }
        }
    }
    return d;
}

/** Todos os adquirentes do registo que suportam o método (como na config global); `is_connected` indica se há credencial conectada em algum lugar. */
function gatewaysForSelectMethod(method) {
    return (props.gateways || []).filter((g) => Array.isArray(g.methods) && g.methods.includes(method));
}

const gatewayOrderRows = [
    { key: 'pix', label: 'PIX' },
    { key: 'card', label: 'Cartão' },
    { key: 'boleto', label: 'Boleto' },
    { key: 'pix_auto', label: 'PIX automático' },
];

const showPixAutoRow = computed(() =>
    (props.gateways || []).some((g) => Array.isArray(g.methods) && g.methods.includes('pix_auto'))
);

const merchantGatewayPrimary = reactive({
    pix: '',
    card: '',
    boleto: '',
    pix_auto: '',
});

/**
 * Mesma ideia da aba Financeiro → Adquirentes: lista completa com redundância (principal primeiro).
 * @param {string} method
 * @param {string} primarySlug
 */
function buildGatewayOrderListForMerchant(method, primarySlug) {
    if (!primarySlug) {
        return null;
    }
    const u = editUser.value;
    const platformPrev = (props.platform_gateway_order && props.platform_gateway_order[method]) || [];
    const merchantPrev = (u?.merchant_gateway_order && u.merchant_gateway_order[method]) || [];
    const prev = merchantPrev.length ? merchantPrev : platformPrev;
    const available = gatewaysForSelectMethod(method).map((g) => g.slug);
    if (available.length === 0) {
        return null;
    }
    if (!available.includes(primarySlug)) {
        const filtered = prev.filter((s) => available.includes(s));
        return filtered.length ? filtered : [...available];
    }
    const rest = [];
    const seen = new Set([primarySlug]);
    for (const s of prev) {
        if (!seen.has(s) && available.includes(s)) {
            rest.push(s);
            seen.add(s);
        }
    }
    for (const s of available) {
        if (!seen.has(s)) {
            rest.push(s);
            seen.add(s);
        }
    }
    return [primarySlug, ...rest];
}

function syncMerchantPrimaryFromUser(u) {
    const pOrder = props.platform_gateway_order || {};
    for (const method of ['pix', 'card', 'boleto', 'pix_auto']) {
        const slugs = gatewaysForSelectMethod(method).map((g) => g.slug);
        if (!slugs.length) {
            merchantGatewayPrimary[method] = '';
            continue;
        }
        const mo = u.merchant_gateway_order?.[method];
        const hasMerchantOverride = Array.isArray(mo) && mo.length > 0;
        if (!hasMerchantOverride) {
            merchantGatewayPrimary[method] = '';
            continue;
        }
        const first = mo.find((s) => slugs.includes(s));
        merchantGatewayPrimary[method] = first || '';
    }
}

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    account_status: 'approved',
    admin_withdrawal_blocked: false,
    admin_blocked_amount: '',
    admin_block_until: '',
    admin_block_note: '',
    merchant_fees: defaultFeeOverrides(),
    merchant_settlement_overrides: defaultSettlementOverrides(),
});

const isEditModalOpen = computed(() => editUser.value !== null);

function openEditModal(u) {
    editUser.value = u;
    editForm.name = u.name;
    editForm.email = u.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.account_status = u.account_status || 'approved';
    const wa = u.wallet_admin;
    editForm.admin_withdrawal_blocked = !!(wa && wa.admin_withdrawal_blocked);
    editForm.admin_blocked_amount =
        wa && wa.admin_blocked_amount != null && wa.admin_blocked_amount !== '' ? String(wa.admin_blocked_amount) : '';
    editForm.admin_block_until = formatBlockUntilForInput(wa?.admin_block_until);
    editForm.admin_block_note = wa?.admin_block_note || '';
    editForm.merchant_fees = mergeFeeOverrides(u.merchant_fees);
    editForm.merchant_settlement_overrides = mergeSettlementOverrides(u.merchant_settlement_overrides);
    syncMerchantPrimaryFromUser(u);
    editForm.clearErrors();
}

function closeEditModal() {
    editUser.value = null;
}

function submitEdit() {
    if (!editUser.value) return;
    editForm
        .transform((data) => {
            const order = {};
            for (const m of ['pix', 'card', 'boleto', 'pix_auto']) {
                const p = merchantGatewayPrimary[m];
                if (!p) {
                    continue;
                }
                const built = buildGatewayOrderListForMerchant(m, p);
                if (built && built.length) {
                    order[m] = built;
                }
            }

            return {
                ...data,
                merchant_gateway_order: Object.keys(order).length ? order : null,
            };
        })
        .put(`/plataforma/usuarios/${editUser.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
}

function destroyUser(id) {
    if (!confirm('Excluir este infoprodutor? Esta ação não pode ser desfeita.')) return;
    deletingId.value = id;
    router.delete(`/plataforma/usuarios/${id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
}

function statusLabel(s) {
    const map = {
        approved: 'Aprovado',
        pending: 'Pendente',
        rejected: 'Rejeitado',
        suspended: 'Suspenso',
        blocked: 'Bloqueado',
    };
    return map[s] || s || '—';
}

function formatBlockUntilForInput(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Infoprodutores</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Saldo, documento e status da conta</p>
            </div>
            <Link
                href="/plataforma/usuarios/create"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                <UserPlus class="h-4 w-4" />
                Novo infoprodutor
            </Link>
        </div>

        <p
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </p>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/60">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Documento</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-right">PIX pend.</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="u in users" :key="u.id" class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ u.name }}</td>
                        <td class="max-w-[200px] truncate px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ u.email }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ u.document || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs dark:bg-zinc-800">{{ statusLabel(u.account_status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ formatBRL(u.saldo_disponivel) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-zinc-500">{{ formatBRL(u.saldo_pix) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <Link
                                    :href="`/plataforma/verificacoes-kyc/usuario/${u.id}`"
                                    class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-[var(--color-primary)] dark:hover:bg-zinc-800"
                                    title="Ver dados e documentos (KYC)"
                                >
                                    <Eye class="h-4 w-4" />
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800"
                                    title="Editar"
                                    @click="openEditModal(u)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40"
                                    title="Excluir"
                                    :disabled="deletingId === u.id"
                                    @click="destroyUser(u.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.length">
                        <td colspan="7" class="px-4 py-10 text-center text-zinc-500">Nenhum infoprodutor cadastrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal editar -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Editar infoprodutor</h3>
                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closeEditModal">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                        <input v-model="editForm.name" type="text" required class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800" />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                        <input v-model="editForm.email" type="email" required class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800" />
                        <p v-if="editForm.errors.email" class="mt-1 text-sm text-red-600">{{ editForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nova senha (opcional)</label>
                        <input v-model="editForm.password" type="password" minlength="8" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800" />
                        <p v-if="editForm.errors.password" class="mt-1 text-sm text-red-600">{{ editForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Confirmar senha</label>
                        <input v-model="editForm.password_confirmation" type="password" minlength="8" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800" />
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                        <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-zinc-200">Conta e acesso ao painel</p>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Status da conta</label>
                        <select
                            v-model="editForm.account_status"
                            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                        >
                            <option value="approved">Aprovado</option>
                            <option value="pending">Pendente</option>
                            <option value="rejected">Rejeitado</option>
                            <option value="suspended">Suspenso (não acessa o painel)</option>
                            <option value="blocked">Bloqueado (não acessa o painel)</option>
                        </select>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Suspenso ou bloqueado: o infoprodutor e a equipe não conseguem entrar no painel do vendedor.
                        </p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-zinc-200">Saldo e saques</p>
                        <p v-if="editUser" class="mb-3 text-xs text-zinc-600 dark:text-zinc-400">
                            Disponível (total): <strong class="text-zinc-800 dark:text-zinc-200">{{ formatBRL(editUser.saldo_disponivel) }}</strong>
                            · PIX pendente: <strong class="text-zinc-800 dark:text-zinc-200">{{ formatBRL(editUser.saldo_pix) }}</strong>
                            · MED (contestação, ref. carteira):
                            <strong class="text-zinc-800 dark:text-zinc-200">{{ formatBRL(editUser.med_total ?? 0) }}</strong>
                        </p>
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <input v-model="editForm.admin_withdrawal_blocked" type="checkbox" class="rounded border-zinc-300" />
                            Bloquear todos os saques (administrativo)
                        </label>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Valor adicional bloqueado (R$)</label>
                            <input
                                v-model="editForm.admin_blocked_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0,00"
                                class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Reduz o saldo disponível para saque neste valor (por carteira ao solicitar). MED já retira valor do disponível automaticamente.
                            </p>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Bloqueio automático até (opcional)</label>
                            <input
                                v-model="editForm.admin_block_until"
                                type="datetime-local"
                                class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Após esta data/hora, bloqueio total e valor extra são limpos automaticamente.</p>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Observação interna (opcional)</label>
                            <input
                                v-model="editForm.admin_block_note"
                                type="text"
                                maxlength="500"
                                class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                            />
                        </div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-zinc-200">Taxas (opcional)</p>
                        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
                            Sobrescreve os padrões da plataforma em Financeiro → Taxas. Deixe em branco para usar só a
                            plataforma.
                        </p>
                        <div class="space-y-3 text-sm">
                            <div v-for="row in ['pix', 'card', 'boleto', 'withdrawal']" :key="row" class="grid gap-2 sm:grid-cols-[100px_1fr_1fr] sm:items-center">
                                <span class="font-medium capitalize text-zinc-700 dark:text-zinc-300">{{
                                    row === 'withdrawal' ? 'Saque' : row === 'pix' ? 'PIX' : row === 'card' ? 'Cartão' : 'Boleto'
                                }}</span>
                                <input
                                    v-model="editForm.merchant_fees[row].percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="%"
                                    class="rounded-lg border border-zinc-300 px-2 py-1.5 dark:border-zinc-600 dark:bg-zinc-800"
                                />
                                <input
                                    v-model="editForm.merchant_fees[row].fixed"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="Fixo R$"
                                    class="rounded-lg border border-zinc-300 px-2 py-1.5 dark:border-zinc-600 dark:bg-zinc-800"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-zinc-200">Liquidação (opcional)</p>
                        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
                            Sobrescreve Financeiro → Liquidação. Deixe em branco para herdar da plataforma.
                        </p>
                        <div class="space-y-3 text-sm">
                            <div
                                v-for="row in ['pix', 'card', 'boleto']"
                                :key="'set-' + row"
                                class="grid gap-2 sm:grid-cols-[100px_1fr_1fr_1fr] sm:items-center"
                            >
                                <span class="font-medium capitalize text-zinc-700 dark:text-zinc-300">{{
                                    row === 'pix' ? 'PIX' : row === 'card' ? 'Cartão' : 'Boleto'
                                }}</span>
                                <input
                                    v-model="editForm.merchant_settlement_overrides[row].days_to_available"
                                    type="number"
                                    min="0"
                                    max="365"
                                    step="1"
                                    placeholder="Dias D+N"
                                    class="rounded-lg border border-zinc-300 px-2 py-1.5 dark:border-zinc-600 dark:bg-zinc-800"
                                />
                                <input
                                    v-model="editForm.merchant_settlement_overrides[row].reserve_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="Reserva %"
                                    class="rounded-lg border border-zinc-300 px-2 py-1.5 dark:border-zinc-600 dark:bg-zinc-800"
                                />
                                <input
                                    v-model="editForm.merchant_settlement_overrides[row].reserve_hold_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    step="1"
                                    placeholder="Extra reserva (dias)"
                                    class="rounded-lg border border-zinc-300 px-2 py-1.5 dark:border-zinc-600 dark:bg-zinc-800"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-zinc-200">Ordem de adquirentes (opcional)</p>
                        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">
                            Escolha o adquirente principal por forma de pagamento (entre os já conectados na plataforma). «Padrão da
                            plataforma» herda a ordem de Financeiro → Adquirentes.
                        </p>
                        <div class="space-y-3 text-sm">
                            <template v-for="row in gatewayOrderRows" :key="'go-' + row.key">
                                <div
                                    v-if="row.key !== 'pix_auto' || showPixAutoRow"
                                    class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3"
                                >
                                    <label class="w-40 shrink-0 font-medium text-zinc-700 dark:text-zinc-300">{{ row.label }}</label>
                                    <select
                                        v-model="merchantGatewayPrimary[row.key]"
                                        class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                                    >
                                        <option value="">Padrão da plataforma</option>
                                        <option v-for="g in gatewaysForSelectMethod(row.key)" :key="g.slug" :value="g.slug">
                                            {{ g.name }}{{ g.is_connected ? '' : ' (não conectado)' }}
                                        </option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" @click="closeEditModal">Cancelar</Button>
                        <Button type="submit" :disabled="editForm.processing">Salvar</Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
