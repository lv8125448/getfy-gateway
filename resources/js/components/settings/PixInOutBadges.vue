<script setup>
import { computed } from 'vue';

const props = defineProps({
    slug: { type: String, default: '' },
});

/** Cobrança PIX + saque PIX (plataforma). */
const SLUGS_PIX_IN_OUT = new Set(['cajupay', 'spacepag', 'woovi']);
/** Só cobrança PIX (sem payout automático destes adquirentes nesta integração). */
const SLUGS_PIX_IN_ONLY = new Set(['efi', 'mercadopago', 'pagarme']);

const s = computed(() => (props.slug || '').toLowerCase());

const showPixInOut = computed(() => SLUGS_PIX_IN_OUT.has(s.value));
const showPixInOnly = computed(() => SLUGS_PIX_IN_ONLY.has(s.value));
const visible = computed(() => showPixInOut.value || showPixInOnly.value);
</script>

<template>
    <span v-if="visible" class="inline-flex flex-wrap items-center gap-1">
        <span
            class="rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            title="Cobrança / recebimento PIX"
        >
            PIX in
        </span>
        <span
            v-if="showPixInOut"
            class="rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200"
            title="Saque / transferência PIX"
        >
            PIX out
        </span>
    </span>
</template>
