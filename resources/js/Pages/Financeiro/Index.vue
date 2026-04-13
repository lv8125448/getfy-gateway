<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/composables/useI18n';
import {
    Wallet,
    ArrowDownCircle,
    Clock,
    Shield,
    Zap,
    X,
    Landmark,
    FileText,
    UserCircle,
} from 'lucide-vue-next';
import KycDocumentsForm from '@/components/kyc/KycDocumentsForm.vue';

defineOptions({ layout: LayoutInfoprodutor });

const page = usePage();
const { t } = useI18n();

const props = defineProps({
    wallet: { type: Object, default: null },
    withdrawals: { type: Array, default: () => [] },
    fee_preview: { type: Object, default: () => ({}) },
    payout_settings: { type: Object, default: () => ({}) },
    payout_pix_setup: { type: String, default: null },
    settlement_preview: { type: Object, default: () => ({}) },
    seller_profile: {
        type: Object,
        default: () => ({ name: '', email: '', document: null }),
    },
    kyc_finance_locked: { type: Boolean, default: false },
    kyc_status: { type: String, default: null },
    kyc_person_type: { type: String, default: 'pf' },
    kyc_rejection_reason: { type: String, default: null },
    /** Dados do cadastro inicial (somente leitura). */
    registration_snapshot: {
        type: Object,
        default: () => ({}),
    },
});

function snap(v) {
    if (v === null || v === undefined || v === '') {
        return '—';
    }
    return v;
}

function readTabFromUrl() {
    if (typeof window === 'undefined') {
        return 'extrato';
    }
    const params = new URLSearchParams(window.location.search);
    const t = params.get('tab');
    if (t === 'seus-dados' || t === 'dados' || t === 'extrato') {
        return t;
    }
    return 'extrato';
}

const activeTab = ref('extrato');

function setFinanceTab(tab) {
    activeTab.value = tab;
    if (typeof window === 'undefined') {
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

onMounted(() => {
    activeTab.value = readTabFromUrl();
});
const showWithdrawModal = ref(false);
/** Só após cadastro: mostra dados em leitura; Editar habilita o formulário. */
const editingPayoutPix = ref(false);

const withdrawForm = useForm({
    amount: '',
    bucket: 'pix',
    notes: '',
});

const payoutPixForm = useForm({
    label: '',
    pix_key_type: 'cpf',
    pix_key: '',
    receiver_name: '',
    receiver_document: '',
    receiver_email: '',
});

function openWithdrawModal() {
    showWithdrawModal.value = true;
}

function closeWithdrawModal() {
    showWithdrawModal.value = false;
}

function submitPayoutPix() {
    payoutPixForm.clearErrors();
    const onSuccess = () => {
        payoutPixForm.clearErrors();
        editingPayoutPix.value = false;
        if (props.payout_pix_setup === 'label_and_key') {
            payoutPixForm.reset('pix_key');
        }
    };
    if (props.payout_pix_setup === 'label_and_key') {
        payoutPixForm
            .transform((data) => ({
                label: data.label,
                pix_key_type: data.pix_key_type,
                pix_key: data.pix_key,
            }))
            .post('/financeiro/pix-saque', {
                preserveScroll: true,
                onSuccess,
            });
        return;
    }
    if (props.payout_pix_setup === 'key_and_receiver') {
        payoutPixForm
            .transform((data) => ({
                pix_key: data.pix_key,
                pix_key_type: data.pix_key_type,
                receiver_name: data.receiver_name,
                receiver_document: data.receiver_document,
                receiver_email: data.receiver_email,
            }))
            .post('/financeiro/pix-saque', {
                preserveScroll: true,
                onSuccess,
            });
        return;
    }
    if (props.payout_pix_setup === 'pix_key_only') {
        payoutPixForm
            .transform((data) => ({
                pix_key: data.pix_key,
                pix_key_type: data.pix_key_type,
            }))
            .post('/financeiro/pix-saque', {
                preserveScroll: true,
                onSuccess,
            });
    }
}

function syncPayoutPixFormFromProps() {
    const s = props.payout_settings || {};
    if (props.payout_pix_setup === 'label_and_key') {
        payoutPixForm.label = (s.payout_pix_label || s.cajupay_pix_label || '').trim();
        payoutPixForm.pix_key = '';
        payoutPixForm.pix_key_type = 'cpf';
    } else if (props.payout_pix_setup === 'key_and_receiver') {
        payoutPixForm.pix_key = (s.payout_pix_key || s.spacepag_pix_key || '').trim();
        payoutPixForm.pix_key_type = s.payout_pix_key_type || s.spacepag_pix_key_type || 'cpf';
        payoutPixForm.receiver_name = (s.receiver_name || '').trim();
        payoutPixForm.receiver_document = (s.receiver_document || '').trim();
        payoutPixForm.receiver_email = (s.receiver_email || '').trim();
    } else if (props.payout_pix_setup === 'pix_key_only') {
        payoutPixForm.pix_key = (s.payout_pix_key || s.woovi_pix_key || '').trim();
        payoutPixForm.pix_key_type = s.payout_pix_key_type || s.woovi_pix_key_type || 'cpf';
    }
}

function startEditPayoutPix() {
    editingPayoutPix.value = true;
    syncPayoutPixFormFromProps();
}

function cancelEditPayoutPix() {
    editingPayoutPix.value = false;
    syncPayoutPixFormFromProps();
    payoutPixForm.clearErrors();
}

function submitWithdraw() {
    withdrawForm.post('/financeiro/saque', {
        preserveScroll: true,
        onSuccess: () => {
            withdrawForm.reset('amount', 'notes');
            withdrawForm.clearErrors();
            showWithdrawModal.value = false;
        },
    });
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
}

function formatPercent(value) {
    const n = Number(value) || 0;
    return `${n.toLocaleString('pt-BR', {
        minimumFractionDigits: Number.isInteger(n) ? 0 : 2,
        maximumFractionDigits: 2,
    })}%`;
}

function bucketLabel(b) {
    const map = { pix: 'PIX', card: 'Cartão', boleto: 'Boleto' };
    return map[b] || b || '—';
}

function statusLabel(s) {
    const map = {
        pending: 'Pendente',
        paid: 'Pago',
        rejected: 'Rejeitado',
    };
    return map[s] || s || '—';
}

const role = computed(() => page.props.auth?.user?.role);
const canRequestWithdrawal = computed(() => role.value === 'infoprodutor');

const kycFinanceLocked = computed(() => props.kyc_finance_locked === true);

const withdrawalFeeHint = computed(() => {
    const w = props.fee_preview?.withdrawal;
    if (!w) return '';
    return `Taxa de saque efetiva: ${w.percent ?? 0}% + ${formatBRL(w.fixed ?? 0)} sobre o valor solicitado.`;
});

const payoutPixLabelDisplay = computed(() => {
    const s = props.payout_settings || {};
    return (s.payout_pix_label || s.cajupay_pix_label || '').trim() || '—';
});

const payoutPixKeyDisplay = computed(() => {
    const s = props.payout_settings || {};
    return (s.payout_pix_key || s.spacepag_pix_key || s.woovi_pix_key || '').trim() || '';
});

function pixKeyTypeLabel(t) {
    const m = { cpf: 'CPF', cnpj: 'CNPJ', email: 'E-mail', phone: 'Telefone', evp: 'Chave aleatória' };
    return m[t] || t || '—';
}

const payoutPixTypeDisplay = computed(() => {
    const s = props.payout_settings || {};
    const t = s.payout_pix_key_type || s.spacepag_pix_key_type || s.woovi_pix_key_type || 'cpf';
    return pixKeyTypeLabel(t);
});

const hasReservePending = computed(() => (Number(props.wallet?.reserve_pending_total) || 0) > 0.0001);

const settlementCards = computed(() => {
    const fees = props.fee_preview || {};
    const sp = props.settlement_preview || {};
    const rows = [
        { key: 'pix', label: 'PIX', accent: 'from-sky-500/20 to-cyan-500/10 text-sky-700 dark:text-sky-300' },
        { key: 'card', label: 'Cartão', accent: 'from-violet-500/20 to-purple-500/10 text-violet-700 dark:text-violet-300' },
        { key: 'boleto', label: 'Boleto', accent: 'from-emerald-500/20 to-teal-500/10 text-emerald-700 dark:text-emerald-300' },
    ];
    return rows
        .map(({ key, label, accent }) => {
            const r = sp[key];
            const f = fees[key];
            if (!r || typeof r !== 'object') return null;
            const days = Number(r.days_to_available) || 0;
            const percent = Number(f?.percent) || 0;
            const fixed = Number(f?.fixed) || 0;
            return {
                label,
                accent,
                percent,
                fixed,
                days,
                payoutText: `D+${days}`,
            };
        })
        .filter(Boolean);
});

const hasPayoutPixRegistered = computed(() => {
    const s = props.payout_settings || {};
    if (props.payout_pix_setup === 'label_and_key') {
        return !!s.cajupay_pix_key_id;
    }
    if (props.payout_pix_setup === 'key_and_receiver' || props.payout_pix_setup === 'pix_key_only') {
        const k = (s.payout_pix_key || s.spacepag_pix_key || s.woovi_pix_key || '').trim();
        return k !== '';
    }
    return false;
});

const hasExtratoContent = computed(() => (props.withdrawals?.length || 0) > 0);

watch(
    () => [props.payout_pix_setup, props.payout_settings],
    () => {
        syncPayoutPixFormFromProps();
    },
    { immediate: true }
);

const inputClass =
    'mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white';
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ t('sidebar.finance', 'Financeiro') }}</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ t('finance.subtitle', 'Saldos, extrato e dados para recebimento.') }}
                </p>
            </div>
            <div v-if="canRequestWithdrawal && !kycFinanceLocked" class="shrink-0">
                <Button type="button" class="inline-flex items-center gap-2" @click="openWithdrawModal">
                    <ArrowDownCircle class="h-4 w-4" aria-hidden="true" />
                    {{ t('finance.request_withdrawal', 'Solicitar saque') }}
                </Button>
            </div>
        </div>

        <p
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </p>
        <p
            v-if="page.props.flash?.error"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            {{ page.props.flash.error }}
        </p>

        <div
            v-if="canRequestWithdrawal && kycFinanceLocked"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            <strong>Verificação KYC pendente.</strong>
            Conclua o envio dos documentos para cadastrar chave PIX e solicitar saques.
            <Link href="/financeiro?tab=seus-dados" class="ml-1 font-semibold text-amber-800 underline hover:text-amber-950 dark:text-amber-200">Enviar documentos</Link>
        </div>

        <!-- Grelha de saldos -->
        <section v-if="wallet" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="relative overflow-hidden rounded-2xl border-2 border-[var(--color-primary)]/35 bg-white p-5 shadow-sm dark:border-[var(--color-primary)]/40 dark:bg-zinc-900/80"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                            <Zap class="h-5 w-5" aria-hidden="true" />
                        </div>
                    </div>
                    <p class="mt-3 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ t('finance.available_balance', 'Saldo disponível') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ formatBRL(wallet.available_total) }}</p>
                    <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ t('finance.ready_for_withdrawal', 'Pronto para saque') }}</p>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/80">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-400">
                        <Clock class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <p class="mt-3 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ t('finance.pending_receive', 'A receber') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ formatBRL(wallet.pending_total) }}</p>
                    <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ t('finance.settling', 'Em liquidação') }}</p>
                </div>

                <div
                    v-if="hasReservePending"
                    class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/80"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-500/10 text-violet-700 dark:text-violet-300">
                        <Shield class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <p class="mt-3 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Reserva financeira</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">
                        {{ formatBRL(wallet.reserve_pending_total) }}
                    </p>
                    <p class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">Retida até liberar</p>
                </div>

                <div
                    class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/80 sm:col-span-2 xl:col-span-1"
                >
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <Wallet class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <p class="mt-3 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ t('finance.by_method', 'Por método') }}</p>
                    <div class="mt-2 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-2 tabular-nums">
                            <span class="text-zinc-500">PIX</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ formatBRL(wallet.available_pix) }}</span>
                        </div>
                        <div class="flex justify-between gap-2 tabular-nums">
                            <span class="text-zinc-500">Cartão</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ formatBRL(wallet.available_card) }}</span>
                        </div>
                        <div class="flex justify-between gap-2 tabular-nums">
                            <span class="text-zinc-500">Boleto</span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ formatBRL(wallet.available_boleto) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <details
                v-if="settlementCards.length"
                class="group rounded-xl border border-zinc-200/80 bg-zinc-50/50 px-4 py-3 dark:border-zinc-700/80 dark:bg-zinc-800/30"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 [&::-webkit-details-marker]:hidden"
                >
                    {{ t('finance.my_fees_and_payout', 'Minhas taxas e prazo') }}
                    <span class="ml-auto text-xs font-normal text-zinc-500 group-open:hidden">{{ t('common.view', 'Ver') }}</span>
                    <span class="ml-auto hidden text-xs font-normal text-zinc-500 group-open:inline">{{ t('common.hide', 'Ocultar') }}</span>
                </summary>
                <p class="mt-3 border-t border-zinc-200/80 pt-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ t('finance.by_payment_method', 'Por método de pagamento') }}
                </p>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    <article
                        v-for="card in settlementCards"
                        :key="card.label"
                        class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/80"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ card.label }}</p>
                            <span
                                class="rounded-full bg-gradient-to-r px-2.5 py-1 text-[10px] font-semibold"
                                :class="card.accent"
                            >
                                {{ t('finance.fee', 'Taxa') }}
                            </span>
                        </div>
                        <p class="mt-4 text-center text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">
                            {{ formatPercent(card.percent) }}
                        </p>
                        <p class="mt-2 text-center text-xs text-zinc-500 dark:text-zinc-400">
                            + {{ formatBRL(card.fixed) }} fixo
                        </p>
                        <div class="mt-4 border-t border-zinc-100 pt-3 text-center dark:border-zinc-700">
                            <p class="text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ t('finance.payout_deadline', 'Prazo de saque') }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ card.payoutText }}</p>
                        </div>
                    </article>
                </div>
            </details>
        </section>

        <!-- Abas -->
        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <button
                    type="button"
                    class="flex items-center gap-2 border-b-2 px-5 py-3.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'extrato'
                            ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                            : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'
                    "
                    @click="setFinanceTab('extrato')"
                >
                    <FileText class="h-4 w-4" aria-hidden="true" />
                    {{ t('finance.statement', 'Extrato') }}
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 border-b-2 px-5 py-3.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'seus-dados'
                            ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                            : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'
                    "
                    @click="setFinanceTab('seus-dados')"
                >
                    <UserCircle class="h-4 w-4" aria-hidden="true" />
                    Seus dados
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 border-b-2 px-5 py-3.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'dados'
                            ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                            : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'
                    "
                    @click="setFinanceTab('dados')"
                >
                    <Landmark class="h-4 w-4" aria-hidden="true" />
                    {{ t('finance.bank_details', 'Dados bancários') }}
                </button>
            </div>

            <div class="p-5 sm:p-6">
                <!-- Extrato -->
                <div v-show="activeTab === 'extrato'" class="space-y-8">
                    <div v-if="!hasExtratoContent" class="rounded-xl border border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
                        <FileText class="mx-auto h-10 w-10 text-zinc-300 dark:text-zinc-600" aria-hidden="true" />
                        <p class="mt-3 text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ t('finance.no_withdrawals', 'Nenhum saque ainda') }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ t('finance.no_withdrawals_hint', 'Seus saques solicitados aparecerão aqui.') }}</p>
                    </div>

                    <div v-else>
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">{{ t('finance.withdrawals', 'Saques') }}</h3>
                            <div class="overflow-x-auto rounded-xl border border-zinc-100 dark:border-zinc-700/80">
                                <table class="w-full min-w-[640px] text-left text-sm">
                                    <thead class="border-b border-zinc-200 bg-zinc-50/80 text-xs uppercase text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50">
                                        <tr>
                                            <th class="px-4 py-3">Data</th>
                                            <th class="px-4 py-3">Carteira</th>
                                            <th class="px-4 py-3 text-right">Bruto</th>
                                            <th class="px-4 py-3 text-right">Taxa</th>
                                            <th class="px-4 py-3 text-right">Líquido</th>
                                            <th class="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                        <tr v-for="w in withdrawals" :key="w.id" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                            <td class="px-4 py-2.5 whitespace-nowrap text-zinc-600 dark:text-zinc-300">
                                                {{ w.created_at ? new Date(w.created_at).toLocaleString('pt-BR') : '—' }}
                                            </td>
                                            <td class="px-4 py-2.5">{{ bucketLabel(w.bucket) }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums">{{ formatBRL(w.amount) }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-zinc-500">{{ formatBRL(w.fee_amount) }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums">{{ formatBRL(w.net_amount) }}</td>
                                            <td class="px-4 py-2.5">
                                                <span
                                                    class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                                    :class="
                                                        w.status === 'paid'
                                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
                                                            : w.status === 'rejected'
                                                              ? 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200'
                                                              : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'
                                                    "
                                                >
                                                    {{ statusLabel(w.status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seus dados (cadastro + KYC) -->
                <div v-show="activeTab === 'seus-dados'" class="space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Dados do cadastro</h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            Informações preenchidas no cadastro. Para alterar, entre em contato com o suporte da plataforma.
                        </p>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase text-zinc-500">Tipo de conta</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.person_type_label) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Nome completo</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.name) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">E-mail</dt>
                                <dd class="mt-0.5 break-all text-zinc-900 dark:text-white">{{ snap(registration_snapshot.email) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Data de nascimento</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.birth_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">
                                    {{ registration_snapshot.person_type === 'pj' ? 'CNPJ' : 'CPF' }}
                                </dt>
                                <dd class="mt-0.5 font-mono text-zinc-900 dark:text-white">{{ snap(registration_snapshot.document) }}</dd>
                            </div>
                            <template v-if="registration_snapshot.person_type === 'pj'">
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase text-zinc-500">Razão social</dt>
                                    <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.company_name) }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase text-zinc-500">CPF do representante legal</dt>
                                    <dd class="mt-0.5 font-mono text-zinc-900 dark:text-white">{{ snap(registration_snapshot.legal_representative_cpf) }}</dd>
                                </div>
                            </template>
                            <div class="sm:col-span-2 border-t border-zinc-200 pt-4 dark:border-zinc-600">
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Endereço</p>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">CEP</dt>
                                <dd class="mt-0.5 font-mono text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_zip) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">UF</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_state) }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase text-zinc-500">Logradouro</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_street) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Número</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_number) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Complemento</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_complement) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Bairro</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_neighborhood) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Cidade</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.address_city) }}</dd>
                            </div>
                            <div class="sm:col-span-2 border-t border-zinc-200 pt-4 dark:border-zinc-600">
                                <dt class="text-xs font-medium uppercase text-zinc-500">Faturamento mensal estimado (cadastro)</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ snap(registration_snapshot.monthly_revenue_label) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <KycDocumentsForm
                            embedded
                            :person_type="kyc_person_type"
                            :kyc_status="kyc_status || 'not_submitted'"
                            :rejection_reason="kyc_rejection_reason"
                        />
                    </div>
                </div>

                <!-- Dados bancários -->
                <div v-show="activeTab === 'dados'" class="space-y-6">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Titular da conta</h3>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Nome</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ seller_profile.name || page.props.auth?.user?.name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">E-mail</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ seller_profile.email || page.props.auth?.user?.email || '—' }}</dd>
                            </div>
                            <div v-if="seller_profile.document" class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase text-zinc-500">Documento</dt>
                                <dd class="mt-0.5 text-zinc-900 dark:text-white">{{ seller_profile.document }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="canRequestWithdrawal && payout_pix_setup && !kycFinanceLocked" class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Chave PIX e recebimento</h3>
                            <div v-if="hasPayoutPixRegistered" class="flex flex-wrap gap-2">
                                <Button
                                    v-if="!editingPayoutPix"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="startEditPayoutPix"
                                >
                                    Editar
                                </Button>
                                <Button
                                    v-if="editingPayoutPix"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="cancelEditPayoutPix"
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </div>

                        <!-- Somente leitura (já cadastrado) -->
                        <div
                            v-if="hasPayoutPixRegistered && !editingPayoutPix"
                            class="max-w-lg space-y-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40"
                        >
                            <template v-if="payout_pix_setup === 'label_and_key'">
                                <dl class="space-y-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Identificação</dt>
                                        <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ payoutPixLabelDisplay }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Chave PIX</dt>
                                        <dd class="mt-1 text-zinc-700 dark:text-zinc-200">
                                            Cadastrada na plataforma de pagamentos. Clique em <strong>Editar</strong> para informar uma nova chave ao
                                            salvar.
                                        </dd>
                                    </div>
                                </dl>
                            </template>
                            <template v-else-if="payout_pix_setup === 'pix_key_only'">
                                <dl class="space-y-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Tipo da chave</dt>
                                        <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ payoutPixTypeDisplay }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Chave PIX</dt>
                                        <dd class="mt-1 break-all font-medium text-zinc-900 dark:text-white">{{ payoutPixKeyDisplay || '—' }}</dd>
                                    </div>
                                </dl>
                            </template>
                            <template v-else-if="payout_pix_setup === 'key_and_receiver'">
                                <dl class="space-y-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Tipo da chave</dt>
                                        <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ payoutPixTypeDisplay }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Chave PIX</dt>
                                        <dd class="mt-1 break-all font-medium text-zinc-900 dark:text-white">{{ payoutPixKeyDisplay || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">Nome do recebedor</dt>
                                        <dd class="mt-1 text-zinc-900 dark:text-white">{{ (payout_settings.receiver_name || '').trim() || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">CPF/CNPJ do recebedor</dt>
                                        <dd class="mt-1 text-zinc-900 dark:text-white">{{ (payout_settings.receiver_document || '').trim() || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase text-zinc-500">E-mail do recebedor</dt>
                                        <dd class="mt-1 break-all text-zinc-900 dark:text-white">{{ (payout_settings.receiver_email || '').trim() || '—' }}</dd>
                                    </div>
                                </dl>
                            </template>
                        </div>

                        <form v-if="!hasPayoutPixRegistered || editingPayoutPix" class="max-w-lg space-y-4" @submit.prevent="submitPayoutPix">
                            <template v-if="payout_pix_setup === 'label_and_key'">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Identificação</label>
                                    <input v-model="payoutPixForm.label" type="text" required maxlength="120" :class="inputClass" placeholder="Ex.: conta principal" />
                                    <p v-if="payoutPixForm.errors.label" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.label }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipo da chave</label>
                                    <select v-model="payoutPixForm.pix_key_type" :class="inputClass">
                                        <option value="cpf">CPF</option>
                                        <option value="cnpj">CNPJ</option>
                                        <option value="email">E-mail</option>
                                        <option value="phone">Telefone</option>
                                        <option value="evp">Chave aleatória</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Chave PIX</label>
                                    <input v-model="payoutPixForm.pix_key" type="text" required maxlength="120" :class="inputClass" autocomplete="off" />
                                    <p v-if="payoutPixForm.errors.pix_key" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.pix_key }}</p>
                                </div>
                            </template>
                            <template v-else-if="payout_pix_setup === 'pix_key_only'">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipo da chave</label>
                                    <select v-model="payoutPixForm.pix_key_type" :class="inputClass">
                                        <option value="cpf">CPF</option>
                                        <option value="cnpj">CNPJ</option>
                                        <option value="email">E-mail</option>
                                        <option value="phone">Telefone</option>
                                        <option value="evp">Chave aleatória</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Chave PIX</label>
                                    <input v-model="payoutPixForm.pix_key" type="text" required maxlength="120" :class="inputClass" autocomplete="off" />
                                    <p v-if="payoutPixForm.errors.pix_key" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.pix_key }}</p>
                                </div>
                            </template>
                            <template v-else-if="payout_pix_setup === 'key_and_receiver'">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipo da chave</label>
                                    <select v-model="payoutPixForm.pix_key_type" :class="inputClass">
                                        <option value="cpf">CPF</option>
                                        <option value="cnpj">CNPJ</option>
                                        <option value="email">E-mail</option>
                                        <option value="phone">Telefone</option>
                                        <option value="evp">Chave aleatória</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Chave PIX</label>
                                    <input v-model="payoutPixForm.pix_key" type="text" required maxlength="120" :class="inputClass" autocomplete="off" />
                                    <p v-if="payoutPixForm.errors.pix_key" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.pix_key }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome do recebedor</label>
                                    <input v-model="payoutPixForm.receiver_name" type="text" required maxlength="120" :class="inputClass" />
                                    <p v-if="payoutPixForm.errors.receiver_name" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.receiver_name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">CPF/CNPJ do recebedor</label>
                                    <input v-model="payoutPixForm.receiver_document" type="text" required maxlength="20" :class="inputClass" />
                                    <p v-if="payoutPixForm.errors.receiver_document" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.receiver_document }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail do recebedor</label>
                                    <input v-model="payoutPixForm.receiver_email" type="email" required maxlength="255" :class="inputClass" />
                                    <p v-if="payoutPixForm.errors.receiver_email" class="mt-1 text-sm text-red-600">{{ payoutPixForm.errors.receiver_email }}</p>
                                </div>
                            </template>
                            <div class="flex flex-wrap justify-end gap-2 pt-2">
                                <Button v-if="editingPayoutPix" type="button" variant="outline" @click="cancelEditPayoutPix">Cancelar</Button>
                                <Button type="submit" :disabled="payoutPixForm.processing">{{
                                    payout_pix_setup === 'label_and_key'
                                        ? payout_settings?.cajupay_pix_key_id
                                            ? 'Salvar alterações'
                                            : 'Cadastrar dados'
                                        : payout_pix_setup === 'pix_key_only'
                                          ? hasPayoutPixRegistered
                                              ? 'Salvar chave PIX'
                                              : 'Cadastrar chave PIX'
                                          : hasPayoutPixRegistered
                                            ? 'Salvar dados'
                                            : 'Cadastrar dados'
                                }}</Button>
                            </div>
                        </form>
                    </div>

                    <div
                        v-else-if="canRequestWithdrawal && kycFinanceLocked && payout_pix_setup"
                        class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-100"
                    >
                        Cadastro de chave PIX e edição bloqueados até a verificação KYC ser aprovada pela plataforma.
                        <Link href="/financeiro?tab=seus-dados" class="font-semibold underline">Enviar ou atualizar documentos</Link>
                    </div>

                    <div
                        v-else-if="canRequestWithdrawal && !payout_pix_setup"
                        class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-100"
                    >
                        A plataforma ainda não configurou o recebimento automático de saques. Entre em contato com o suporte se precisar de ajuda.
                    </div>

                    <p v-else class="text-sm text-zinc-600 dark:text-zinc-400">
                        Apenas o titular (infoprodutor) pode alterar dados bancários e chave PIX.
                    </p>
                </div>
            </div>
        </div>

        <section
            v-if="!canRequestWithdrawal"
            class="rounded-xl border border-dashed border-zinc-300 p-5 text-sm text-zinc-600 dark:border-zinc-600 dark:text-zinc-400"
        >
            Apenas o titular da conta (infoprodutor) pode solicitar saques e editar dados de recebimento. Você pode visualizar o extrato acima.
        </section>

        <!-- Modal saque -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showWithdrawModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    aria-modal="true"
                    role="dialog"
                    aria-labelledby="withdraw-modal-title"
                    @keydown.escape="closeWithdrawModal"
                >
                    <div class="absolute inset-0 bg-zinc-900/60 dark:bg-zinc-950/70" aria-hidden="true" @click="closeWithdrawModal" />
                    <div
                        class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                                    <ArrowDownCircle class="h-5 w-5" aria-hidden="true" />
                                </div>
                                <h3 id="withdraw-modal-title" class="text-lg font-semibold text-zinc-900 dark:text-white">Solicitar saque</h3>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                aria-label="Fechar"
                                @click="closeWithdrawModal"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <p v-if="withdrawalFeeHint" class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ withdrawalFeeHint }}</p>
                        <form class="mt-5 space-y-4" @submit.prevent="submitWithdraw">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Valor (R$)</label>
                                <input v-model="withdrawForm.amount" type="number" min="0.01" step="0.01" required :class="inputClass" />
                                <p v-if="withdrawForm.errors.amount" class="mt-1 text-sm text-red-600">{{ withdrawForm.errors.amount }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Carteira</label>
                                <select v-model="withdrawForm.bucket" :class="inputClass">
                                    <option value="pix">PIX</option>
                                    <option value="card">Cartão</option>
                                    <option value="boleto">Boleto</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Observações (opcional)</label>
                                <textarea
                                    v-model="withdrawForm.notes"
                                    rows="2"
                                    :class="inputClass"
                                    placeholder="Referência ou observação"
                                />
                            </div>
                            <div class="flex flex-wrap justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" @click="closeWithdrawModal">Cancelar</Button>
                                <Button type="submit" :disabled="withdrawForm.processing">Enviar solicitação</Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
