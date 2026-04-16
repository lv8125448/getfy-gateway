<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { ExternalLink, Package, X } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

defineProps({
    purchases: { type: Array, default: () => [] },
});

const page = usePage();
const refundOpen = ref(false);
/** Código público do pedido (exibido no texto; o envio usa order_id interno). */
const refundOrderPublicRef = ref('');

const refundForm = useForm({
    order_id: null,
    reason: '',
});

function formatBRL(n) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(n) || 0);
}

function openRefund(row) {
    refundForm.order_id = row.order_id;
    refundOrderPublicRef.value = row.public_reference || String(row.order_id);
    refundForm.reason = '';
    refundForm.clearErrors();
    refundOpen.value = true;
}

function closeRefund() {
    refundOpen.value = false;
    refundOrderPublicRef.value = '';
}

function submitRefund() {
    refundForm.post('/painel-cliente/reembolso', {
        preserveScroll: true,
        onSuccess: () => closeRefund(),
    });
}
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Produtos adquiridos e acesso à área de membros ou entrega.
        </p>

        <div
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"
        >
            {{ page.props.flash.error }}
        </div>

        <div
            v-if="!purchases?.length"
            class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/80 p-10 text-center dark:border-zinc-700 dark:bg-zinc-900/40"
        >
            <Package class="mx-auto h-10 w-10 text-zinc-400" aria-hidden="true" />
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">Ainda não há compras concluídas nesta conta.</p>
        </div>

        <div
            v-else
            class="grid grid-cols-2 gap-2.5 sm:gap-3 md:grid-cols-3 lg:grid-cols-4"
        >
            <article
                v-for="row in purchases"
                :key="row.order_id"
                class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:border-zinc-700"
            >
                <div class="relative h-20 w-full shrink-0 overflow-hidden bg-zinc-100 sm:h-24 dark:bg-zinc-800/80">
                    <img
                        v-if="row.product_image_url"
                        :src="row.product_image_url"
                        :alt="row.product_name"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center text-zinc-400 dark:text-zinc-500"
                    >
                        <Package class="h-8 w-8" aria-hidden="true" />
                    </div>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-2.5 sm:p-3">
                    <div class="min-h-0">
                        <h2
                            class="line-clamp-2 text-xs font-semibold leading-tight text-zinc-900 sm:text-sm dark:text-white"
                        >
                            {{ row.product_name }}
                        </h2>
                        <p class="mt-0.5 text-[10px] text-zinc-500 sm:text-xs">#{{ row.public_reference }}</p>
                        <p class="mt-1 text-xs font-medium text-zinc-700 sm:text-sm dark:text-zinc-300">
                            {{ formatBRL(row.amount) }}
                        </p>
                    </div>
                    <div class="mt-auto flex flex-col gap-1.5">
                        <a
                            v-if="row.access_url"
                            :href="row.access_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full items-center justify-center gap-1 rounded-md bg-[var(--color-primary)] px-2 py-1.5 text-[11px] font-medium text-white transition hover:opacity-90 sm:text-xs"
                        >
                            Acessar
                            <ExternalLink class="h-3 w-3 shrink-0" aria-hidden="true" />
                        </a>
                        <button
                            v-if="row.can_request_refund"
                            type="button"
                            class="rounded-md border border-zinc-300 px-2 py-1.5 text-[11px] font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-800 sm:text-xs"
                            @click="openRefund(row)"
                        >
                            Reembolso
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <Teleport to="body">
            <div
                v-if="refundOpen"
                class="fixed inset-0 z-[200000] flex items-end justify-center bg-black/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                @click.self="closeRefund"
            >
                <div
                    class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Solicitar reembolso</h2>
                        <button
                            type="button"
                            class="rounded-lg p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            aria-label="Fechar"
                            @click="closeRefund"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Pedido #{{ refundOrderPublicRef }}. Descreva o motivo; o vendedor será notificado por e-mail.
                    </p>
                    <textarea
                        v-model="refundForm.reason"
                        rows="5"
                        class="mt-4 w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-950 dark:text-white"
                        placeholder="Motivo da solicitação"
                    />
                    <p v-if="refundForm.errors.reason" class="mt-1 text-xs text-red-600">{{ refundForm.errors.reason }}</p>
                    <p v-if="refundForm.errors.order_id" class="mt-1 text-xs text-red-600">{{ refundForm.errors.order_id }}</p>
                    <div class="mt-4 flex justify-end gap-2">
                        <Button type="button" variant="secondary" @click="closeRefund">Cancelar</Button>
                        <Button type="button" :disabled="refundForm.processing" @click="submitRefund">Enviar solicitação</Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
