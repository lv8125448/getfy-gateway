<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ShieldAlert } from 'lucide-vue-next';

const page = usePage();

const kycStatus = computed(() => page.props.auth?.user?.kyc_status ?? null);

/** Aviso enquanto KYC não estiver aprovado (inclui pendente de envio e em análise). */
const show = computed(() => Boolean(page.props.auth?.user?.needs_kyc_attention));

const message = computed(() => {
    if (kycStatus.value === 'pending_review') {
        return 'Seus documentos estão em análise. Quando a verificação for concluída, liberamos saques e dados bancários conforme a política da plataforma.';
    }
    if (kycStatus.value === 'rejected') {
        return 'Sua verificação precisa de ajustes. Reenvie os documentos na aba Seus dados (Financeiro).';
    }
    return 'Complete a verificação de identidade (KYC) para liberar saques.';
});

const financeiroKycHref = '/financeiro?tab=seus-dados';
</script>

<template>
    <div
        v-if="show"
        class="border-b border-amber-200/80 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/40"
    >
        <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-center gap-3 text-center sm:text-left">
            <ShieldAlert class="h-5 w-5 shrink-0 text-amber-700 dark:text-amber-400" aria-hidden="true" />
            <p class="text-sm text-amber-950 dark:text-amber-100">
                {{ message }}
            </p>
            <Link
                :href="financeiroKycHref"
                class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600"
            >
                {{ kycStatus === 'pending_review' ? 'Ver status' : 'Enviar documentos' }}
            </Link>
        </div>
    </div>
</template>
