<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: LayoutPlatform });

const page = usePage();

const props = defineProps({
    withdrawals: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ withdrawal_status: 'all' }),
    },
});

const withdrawalFilterChips = [
    { withdrawal_status: 'all', label: 'Todos os saques' },
    { withdrawal_status: 'pending', label: 'Pendente' },
    { withdrawal_status: 'paid', label: 'Aprovado' },
    { withdrawal_status: 'rejected', label: 'Rejeitado' },
];

function selectWithdrawalFilter(withdrawalStatus) {
    router.get(
        '/plataforma/saques',
        { withdrawal_status: withdrawalStatus },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function withdrawalChipIsActive(ws) {
    return (props.filters?.withdrawal_status ?? 'all') === ws;
}

function bucketLabel(b) {
    const map = { pix: 'PIX', card: 'Cartão', boleto: 'Boleto' };
    return map[b] || b || '—';
}

function approveWithdrawal(id, manual = false) {
    const msg = manual
        ? 'Marcar como pago manualmente (sem enviar pela API CajuPay)?'
        : 'Enviar o saque via CajuPay (PIX cadastrado do infoprodutor) e marcar como pago?';
    if (!confirm(msg)) return;
    router.post(
        `/plataforma/financeiro/saques/${id}/aprovar`,
        { payout_manual: manual },
        { preserveScroll: true }
    );
}

function rejectWithdrawal(id) {
    const note = window.prompt('Motivo da rejeição (opcional). O saldo será devolvido ao infoprodutor.') || '';
    router.post(`/plataforma/financeiro/saques/${id}/rejeitar`, { admin_note: note }, { preserveScroll: true });
}

function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
}

function withdrawalStatusLabel(status) {
    const map = {
        pending: 'Pendente',
        paid: 'Aprovado',
        rejected: 'Rejeitado',
    };
    return map[status] ?? status ?? '—';
}

function withdrawalStatusBadgeClass(status) {
    if (status === 'paid') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    if (status === 'pending') return 'bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-100';
    if (status === 'rejected') return 'bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200';
    return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200';
}

const withdrawalRows = () => props.withdrawals?.data ?? [];

const paginationLinks = computed(() => props.withdrawals?.links ?? []);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Saques</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Solicitações de saque por status. Em pendentes você pode aprovar (PIX automático ou manual) ou rejeitar (o
                saldo volta ao infoprodutor).
            </p>
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
            class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div class="w-full overflow-x-auto [-webkit-overflow-scrolling:touch]">
                <div
                    class="inline-flex min-w-full flex-wrap gap-2"
                    role="tablist"
                    aria-label="Filtro de status dos saques"
                >
                    <button
                        v-for="chip in withdrawalFilterChips"
                        :key="chip.withdrawal_status"
                        type="button"
                        role="tab"
                        :aria-selected="withdrawalChipIsActive(chip.withdrawal_status)"
                        :class="[
                            'inline-flex items-center gap-2 whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-medium transition',
                            withdrawalChipIsActive(chip.withdrawal_status)
                                ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] dark:bg-[var(--color-primary)]/20'
                                : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900/50 dark:text-zinc-200',
                        ]"
                        @click="selectWithdrawalFilter(chip.withdrawal_status)"
                    >
                        {{ chip.label }}
                    </button>
                </div>
            </div>
        </div>

        <section
            class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
        >
            <div
                class="mb-4 rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100"
            >
                <p class="font-medium">Pendentes</p>
                <p class="mt-1 text-sm opacity-95">
                    O envio do PIX costuma ser <strong>automático</strong> ao solicitar no Financeiro. Use
                    <strong>Pago (CajuPay)</strong> se o envio automático falhou (linha de erro abaixo) ou para reenviar. Use
                    <strong>Pago manual</strong> se o pagamento já foi feito por fora.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-600">
                        <tr>
                            <th class="pb-2 pr-3">Data</th>
                            <th class="pb-2 pr-3">ID</th>
                            <th class="pb-2 pr-3">Infoprodutor</th>
                            <th class="pb-2 pr-3">Carteira</th>
                            <th class="pb-2 pr-3 text-right">Bruto</th>
                            <th class="pb-2 pr-3 text-right">Taxa</th>
                            <th class="pb-2 pr-3 text-right">Líquido</th>
                            <th class="pb-2 pr-3">Status</th>
                            <th class="pb-2 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        <template v-for="w in withdrawalRows()" :key="w.id">
                            <tr>
                                <td class="whitespace-nowrap py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ w.created_at ? new Date(w.created_at).toLocaleString('pt-BR') : '—' }}
                                </td>
                                <td class="py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">#{{ w.id }}</td>
                                <td class="py-3">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ w.infoprodutor_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ w.infoprodutor_email }}</div>
                                </td>
                                <td class="py-3">{{ bucketLabel(w.bucket) }}</td>
                                <td class="py-3 text-right tabular-nums">{{ formatBRL(w.amount) }}</td>
                                <td class="py-3 text-right tabular-nums text-zinc-500">{{ formatBRL(w.fee_amount) }}</td>
                                <td class="py-3 text-right tabular-nums">{{ formatBRL(w.net_amount) }}</td>
                                <td class="py-3">
                                    <div class="flex flex-col gap-1">
                                        <span
                                            :class="[
                                                'inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-medium',
                                                withdrawalStatusBadgeClass(w.status),
                                            ]"
                                        >
                                            {{ withdrawalStatusLabel(w.status) }}
                                        </span>
                                        <span
                                            v-if="w.status === 'paid' && w.payout_manual"
                                            class="text-[10px] font-medium uppercase tracking-wide text-violet-600 dark:text-violet-400"
                                        >
                                            Pago manual
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 text-right">
                                    <div v-if="w.status === 'pending'" class="flex flex-wrap justify-end gap-2">
                                        <Button type="button" size="sm" @click="approveWithdrawal(w.id, false)">
                                            Pago (CajuPay)
                                        </Button>
                                        <Button type="button" size="sm" variant="secondary" @click="approveWithdrawal(w.id, true)">
                                            Pago manual
                                        </Button>
                                        <Button type="button" size="sm" variant="secondary" @click="rejectWithdrawal(w.id)">
                                            Rejeitar
                                        </Button>
                                    </div>
                                    <span v-else class="text-xs text-zinc-400">—</span>
                                </td>
                            </tr>
                            <tr
                                v-if="w.status === 'pending' && w.payout_last_error"
                                class="border-t border-red-100 bg-red-50/80 dark:border-red-900/40 dark:bg-red-950/20"
                            >
                                <td colspan="9" class="px-3 py-2 text-xs text-red-900 dark:text-red-200">
                                    <span class="font-medium">Última tentativa (CajuPay):</span>
                                    {{ w.payout_last_error }}
                                    <span v-if="w.payout_last_attempt_at" class="ml-2 text-red-700/80 dark:text-red-300/80">
                                        ({{ new Date(w.payout_last_attempt_at).toLocaleString('pt-BR') }})
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!withdrawalRows().length">
                            <td colspan="9" class="py-8 text-center text-zinc-500">Nenhum saque encontrado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <nav
            v-if="paginationLinks.length > 3"
            class="flex flex-wrap items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in paginationLinks"
                :key="link.label + String(link.url)"
                :href="link.url || undefined"
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="!link.url"
                :class="[
                    'relative inline-flex min-h-[2.25rem] items-center rounded-lg px-3 py-2 text-sm font-medium transition',
                    link.active
                        ? 'z-10 bg-[var(--color-primary)] text-white'
                        : link.url
                          ? 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'
                          : 'cursor-not-allowed text-zinc-400 dark:text-zinc-500',
                ]"
                v-html="link.label"
                @click.prevent="link.url && router.visit(link.url, { preserveState: true })"
            />
        </nav>
    </div>
</template>
