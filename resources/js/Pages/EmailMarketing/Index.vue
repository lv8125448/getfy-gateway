<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import Button from '@/components/ui/Button.vue';
import { Mail, Settings2, Send, Plus, FileEdit, CheckCircle2, XCircle } from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const props = defineProps({
    campaigns: { type: Array, default: () => [] },
    email_configured: { type: Boolean, default: false },
    cloud_mode: { type: Boolean, default: false },
    cron_instructions: { type: String, default: '' },
    app_url: { type: String, default: '' },
    cron_url: { type: String, default: null },
    base_path: { type: String, default: '' },
    schedule_ok: { type: Boolean, default: false },
    queue_ok: { type: Boolean, default: false },
});

const activeTab = ref('campanhas');

const statusLabel = (status) => {
    const map = { draft: 'Rascunho', sending: 'Enviando', sent: 'Enviado', cancelled: 'Cancelado' };
    return map[status] ?? status;
};

function confirmSend(campaign) {
    if (!props.email_configured) return;
    if (!confirm(`Disparar campanha "${campaign.name}"? Os e-mails serão enviados em lotes de 30 por minuto.`)) return;
    router.post(`/plataforma/email-marketing/${campaign.id}/send`);
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">E-mail Marketing</h1>
            <div class="flex gap-2">
                <Link v-if="activeTab === 'campanhas'" href="/plataforma/email-marketing/create">
                    <Button variant="primary" class="inline-flex items-center gap-2">
                        <Plus class="h-4 w-4" />
                        Nova campanha
                    </Button>
                </Link>
            </div>
        </div>

        <div
            v-if="!email_configured"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30"
        >
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                Configure o e-mail (SMTP, Hostinger, SendGrid etc.) em
                <Link href="/plataforma/configuracoes" class="underline">Configurações &gt; E-mail</Link>
                para poder disparar campanhas.
            </p>
        </div>

        <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors',
                    activeTab === 'campanhas'
                        ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                        : 'border-transparent text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100',
                ]"
                @click="activeTab = 'campanhas'"
            >
                <Mail class="h-4 w-4" />
                Campanhas
            </button>
            <button
                type="button"
                :class="[
                    'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors',
                    activeTab === 'configuracao'
                        ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                        : 'border-transparent text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100',
                ]"
                @click="activeTab = 'configuracao'"
            >
                <Settings2 class="h-4 w-4" />
                Configuração
            </button>
        </div>

        <div v-show="activeTab === 'campanhas'" class="space-y-4">
            <div
                v-if="cloud_mode"
                class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200"
            >
                Se você estiver usando o Getfy em modo cloud, não é necessário configurar o cron; o envio já vem
                configurado automaticamente.
            </div>

            <div v-if="campaigns.length === 0" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white p-8 text-center dark:bg-zinc-800/50">
                <p class="text-zinc-600 dark:text-zinc-400">Nenhuma campanha ainda.</p>
                <Link href="/plataforma/email-marketing/create" class="mt-4 inline-block">
                    <Button variant="primary">Criar primeira campanha</Button>
                </Link>
            </div>

            <div v-else class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/50">
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <li
                        v-for="c in campaigns"
                        :key="c.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ c.name }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ c.subject }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ statusLabel(c.status) }}
                                <template v-if="c.status === 'sending' || c.status === 'sent'">
                                    — {{ c.sent_count ?? 0 }} / {{ c.total_recipients ?? 0 }} enviados
                                </template>
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Link v-if="c.status === 'draft'" :href="`/plataforma/email-marketing/${c.id}/edit`">
                                <Button variant="outline" size="sm" class="inline-flex items-center gap-1">
                                    <FileEdit class="h-3.5 w-3.5" />
                                    Editar
                                </Button>
                            </Link>
                            <Button
                                v-if="c.status === 'draft' && email_configured"
                                variant="primary"
                                size="sm"
                                class="inline-flex items-center gap-1"
                                @click="confirmSend(c)"
                            >
                                <Send class="h-3.5 w-3.5" />
                                Disparar
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-show="activeTab === 'configuracao'" class="space-y-6">
            <div
                v-if="cloud_mode"
                class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30"
            >
                <p class="font-medium text-emerald-800 dark:text-emerald-200">Modo cloud</p>
                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">
                    Se você estiver usando o Getfy em modo cloud, não é necessário configurar o cron; o envio já vem
                    configurado automaticamente.
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white p-4 dark:bg-zinc-800/50">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Status</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    A plataforma verifica automaticamente se o cron e a fila estão rodando (atualizado a cada minuto).
                </p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div
                        :class="[
                            'flex items-center gap-3 rounded-lg border p-4',
                            schedule_ok
                                ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30'
                                : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50',
                        ]"
                    >
                        <CheckCircle2
                            v-if="schedule_ok"
                            class="h-8 w-8 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <XCircle
                            v-else
                            class="h-8 w-8 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <div>
                            <p class="font-medium text-zinc-900 dark:text-white">Cron (agendador)</p>
                            <p class="text-sm" :class="schedule_ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-600 dark:text-zinc-400'">
                                {{ schedule_ok ? 'Configurado e rodando' : 'Não detectado' }}
                            </p>
                        </div>
                    </div>
                    <div
                        :class="[
                            'flex items-center gap-3 rounded-lg border p-4',
                            queue_ok
                                ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30'
                                : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50',
                        ]"
                    >
                        <CheckCircle2
                            v-if="queue_ok"
                            class="h-8 w-8 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <XCircle
                            v-else
                            class="h-8 w-8 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <div>
                            <p class="font-medium text-zinc-900 dark:text-white">Fila (queue worker)</p>
                            <p class="text-sm" :class="queue_ok ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-600 dark:text-zinc-400'">
                                {{ queue_ok ? 'Rodando normalmente' : 'Não detectada' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white p-4 dark:bg-zinc-800/50">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Como configurar</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    Para os e-mails serem enviados em lotes de 30 por minuto, configure o crontab para rodar o agendador a cada minuto e mantenha o worker de fila ativo. A linha abaixo deve ser adicionada ao crontab (<code class="rounded bg-zinc-200 px-1 dark:bg-zinc-700">crontab -e</code>), não executada no terminal.
                </p>
                <p class="mt-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">Cron (uma vez por minuto):</p>
                <pre class="mt-1 overflow-x-auto rounded-lg bg-zinc-100 p-4 text-left font-mono text-sm text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">* * * * * cd {{ base_path || '/caminho/do/projeto' }} && php artisan schedule:run >> /dev/null 2>&1</pre>
                <p class="mt-3 text-xs font-medium text-zinc-500 dark:text-zinc-400">Fila (deixe rodando em outro terminal ou com Supervisor):</p>
                <pre class="mt-1 overflow-x-auto rounded-lg bg-zinc-100 p-4 text-left font-mono text-sm text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200">php artisan queue:work</pre>
                <div v-if="cron_url" class="mt-4 space-y-2">
                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                        URL do cron (para ferramentas externas: cron-job.org, EasyCron, UptimeRobot etc.):
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <code class="rounded-lg bg-zinc-100 px-3 py-2 font-mono text-sm text-zinc-800 break-all dark:bg-zinc-900 dark:text-zinc-200">{{ cron_url }}</code>
                        <button
                            type="button"
                            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            @click="navigator.clipboard?.writeText(cron_url)"
                        >
                            Copiar
                        </button>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Configure a URL para ser chamada a cada minuto. Defina <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-700">CRON_SECRET</code> no .env para gerar o link.
                    </p>
                </div>
                <p v-else class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                    Para usar a URL do cron, adicione <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-700">CRON_SECRET=seu_token_secreto</code> no arquivo .env.
                </p>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    URL da aplicação: <a :href="app_url" class="text-[var(--color-primary)] underline" target="_blank" rel="noopener">{{ app_url }}</a>
                </p>
            </div>
        </div>
    </div>
</template>
