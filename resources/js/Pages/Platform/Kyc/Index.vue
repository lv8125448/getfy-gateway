<script setup>
import { Link } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import { ShieldCheck } from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

defineProps({
    users: { type: Object, required: true },
    filter: { type: String, default: 'pending_review' },
});

const filters = [
    { value: 'pending_review', label: 'Pendentes' },
    { value: 'all', label: 'Todos' },
    { value: 'rejected', label: 'Rejeitados' },
    { value: 'not_submitted', label: 'Sem envio' },
];

function statusLabel(s) {
    const m = {
        not_submitted: 'Sem documentos',
        pending_review: 'Em análise',
        approved: 'Aprovado',
        rejected: 'Rejeitado',
    };
    return m[s] || s || '—';
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-semibold text-zinc-900 dark:text-white">
                    <ShieldCheck class="h-6 w-6 text-[var(--color-primary)]" />
                    Verificações KYC
                </h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Analise documentos enviados pelos infoprodutores.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                v-for="f in filters"
                :key="f.value"
                :href="`/plataforma/verificacoes-kyc?status=${encodeURIComponent(f.value)}`"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="
                    filter === f.value
                        ? 'bg-[var(--color-primary)]/20 text-zinc-900 dark:text-white'
                        : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'
                "
            >
                {{ f.label }}
            </Link>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/40">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-500">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-500">E-mail</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-500">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-500">Status KYC</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-zinc-500">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-white">{{ u.name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">{{ u.email }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ u.person_type === 'pj' ? 'PJ' : 'PF' }}</td>
                        <td class="px-4 py-3 text-sm">{{ statusLabel(u.kyc_status) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/plataforma/verificacoes-kyc/usuario/${u.id}`"
                                class="text-sm font-medium text-[var(--color-primary)] hover:underline"
                            >
                                Abrir
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!users.data?.length" class="px-4 py-10 text-center text-sm text-zinc-500">Nenhum registro neste filtro.</div>
        </div>

        <div v-if="users.links && users.links.length > 3" class="flex flex-wrap justify-center gap-2">
            <Link
                v-for="(l, idx) in users.links"
                :key="idx"
                :href="l.url || '#'"
                class="rounded-lg px-3 py-1.5 text-sm"
                :class="l.active ? 'bg-[var(--color-primary)]/20 font-semibold' : 'bg-zinc-100 dark:bg-zinc-800'"
            >
                <span v-html="l.label" />
            </Link>
        </div>
    </div>
</template>
