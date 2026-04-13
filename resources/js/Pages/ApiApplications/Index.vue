<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { Plus, Pencil, Trash2, KeyRound, ExternalLink, HelpCircle } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    applications: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const deletingId = ref(null);
const helpModalOpen = ref(false);

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function confirmDelete(app) {
    if (!window.confirm(`Excluir a aplicação "${app.name}"? A API key deixará de funcionar.`)) return;
    deletingId.value = app.id;
    router.delete(`/aplicacoes-api/${app.id}`, {
        preserveScroll: true,
        onFinish: () => { deletingId.value = null; },
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        API de Pagamentos
                    </h1>
                    <button
                        type="button"
                        class="rounded-full p-0.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 focus:outline-none focus:ring-2 focus:ring-zinc-400/50"
                        title="O que é a API de Pagamentos?"
                        aria-label="Ajuda"
                        @click="helpModalOpen = true"
                    >
                        <HelpCircle class="h-4 w-4" />
                    </button>
                </div>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Crie aplicações para integrar plataformas externas e processar pagamentos com seus gateways.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as="a" href="/docs/api-pagamentos" target="_blank" rel="noopener noreferrer" variant="outline" size="sm" class="inline-flex items-center gap-2">
                    <ExternalLink class="h-4 w-4" />
                    Abrir documentação
                </Button>
                <Button as="a" href="/aplicacoes-api/create" class="inline-flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Nova aplicação
                </Button>
            </div>
        </div>

        <!-- Modal: O que é a API de Pagamentos -->
        <div
            v-show="helpModalOpen"
            class="fixed inset-0 z-[100000] flex items-center justify-center p-4"
            aria-modal="true"
            role="dialog"
        >
            <div class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70" aria-hidden="true" @click="helpModalOpen = false" />
            <div class="relative max-w-md w-full rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Para que serve a API de Pagamentos?</h2>
                <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    Ela serve para <strong class="text-zinc-800 dark:text-zinc-200">conectar com plataformas externas</strong> ou com seus próprios sistemas e SaaS. Assim você processa pagamentos usando os gateways já configurados no Getfy, sem precisar integrar cada plataforma diretamente com cada método de pagamento.
                </p>
                <ul class="mt-4 space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-zinc-400 dark:bg-zinc-500" />
                        <span><strong class="text-zinc-800 dark:text-zinc-200">Gateway centralizado:</strong> um único roteador de pagamentos; você integra uma vez e usa em várias aplicações.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-zinc-400 dark:bg-zinc-500" />
                        <span><strong class="text-zinc-800 dark:text-zinc-200">Integração única:</strong> não precisa integrar com vários métodos (PIX, cartão, boleto) em cada sistema - tudo passa pela API.</span>
                    </li>
                </ul>
                <div class="mt-6 flex justify-end">
                    <Button variant="outline" size="sm" @click="helpModalOpen = false">Entendi</Button>
                </div>
            </div>
        </div>

        <div v-if="flashSuccess" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ flashSuccess }}
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 overflow-hidden">
            <ul v-if="applications.length" class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <li
                    v-for="app in applications"
                    :key="app.id"
                    class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 hover:bg-zinc-100/80 dark:hover:bg-zinc-700/50 transition-colors"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-zinc-200 dark:bg-zinc-600">
                            <KeyRound class="h-5 w-5 text-zinc-600 dark:text-zinc-300" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ app.name }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ app.slug }}
                                <span v-if="app.webhook_url" class="ml-2 text-zinc-400">· Webhook configurado</span>
                            </p>
                        </div>
                        <span
                            v-if="!app.is_active"
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200"
                        >
                            Inativa
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ formatDate(app.created_at) }}</span>
                        <Button
                            as="a"
                            :href="`/aplicacoes-api/${app.id}/edit`"
                            variant="ghost"
                            size="sm"
                            class="inline-flex items-center gap-1"
                        >
                            <Pencil class="h-4 w-4" />
                            Editar
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="inline-flex items-center gap-1 text-red-600 hover:text-red-700 dark:text-red-400"
                            :disabled="deletingId === app.id"
                            @click="confirmDelete(app)"
                        >
                            <Trash2 class="h-4 w-4" />
                            Excluir
                        </Button>
                    </div>
                </li>
            </ul>
            <div v-else class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                <KeyRound class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-2 font-medium">Nenhuma aplicação</p>
                <p class="mt-1 text-sm">Crie uma aplicação para obter uma API key e integrar com plataformas externas.</p>
                <Button as="a" href="/aplicacoes-api/create" class="mt-4 inline-flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Nova aplicação
                </Button>
            </div>
        </div>
    </div>
</template>
