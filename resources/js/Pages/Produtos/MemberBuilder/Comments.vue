<script setup>
import { ref } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    produto: { type: Object, required: true },
    comments: { type: Object, required: true },
    status_filter: { type: String, default: 'pending' },
});

const statusOptions = [
    { value: 'pending', label: 'Pendentes' },
    { value: 'approved', label: 'Aprovados' },
    { value: 'rejected', label: 'Rejeitados' },
    { value: 'all', label: 'Todos' },
];

const base = () => `/produtos/${props.produto.id}/member-builder/comments`;

function setStatus(s) {
    router.get(base(), { status: s }, { preserveState: true });
}

function approve(commentId) {
    router.put(`/produtos/${props.produto.id}/member-builder/comments/${commentId}`, { status: 'approved' }, { preserveScroll: true });
}

function reject(commentId) {
    router.put(`/produtos/${props.produto.id}/member-builder/comments/${commentId}`, { status: 'rejected' }, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Comentários</h1>
            <Link :href="`/produtos/${produto.id}/member-builder`" class="text-sm text-[var(--color-primary)] hover:underline">Voltar ao Member Builder</Link>
        </div>
        <p class="text-zinc-600 dark:text-zinc-400">{{ produto.name }}</p>

        <div class="flex gap-2">
            <button
                v-for="opt in statusOptions"
                :key="opt.value"
                type="button"
                :class="[
                    'rounded-lg px-3 py-2 text-sm font-medium transition',
                    status_filter === opt.value
                        ? 'bg-[var(--color-primary)] text-white'
                        : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600',
                ]"
                @click="setStatus(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <li v-for="c in comments.data" :key="c.id" class="p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ c.user?.name }} ({{ c.user?.email }})</p>
                            <p v-if="c.lesson" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Aula: {{ c.lesson.title }}</p>
                            <p class="mt-2 text-zinc-700 dark:text-zinc-300">{{ c.content }}</p>
                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ c.status }} · {{ c.created_at }}
                            </p>
                        </div>
                        <div v-if="c.status === 'pending'" class="flex shrink-0 gap-2">
                            <Button size="sm" @click="approve(c.id)">Aprovar</Button>
                            <Button size="sm" variant="outline" @click="reject(c.id)">Rejeitar</Button>
                        </div>
                    </div>
                </li>
            </ul>
            <div v-if="!comments.data?.length" class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                Nenhum comentário encontrado.
            </div>
        </div>

        <div v-if="comments.prev_page_url || comments.next_page_url" class="flex justify-center gap-2">
            <Link v-if="comments.prev_page_url" :href="comments.prev_page_url" class="rounded-lg border border-zinc-200 px-4 py-2 text-sm dark:border-zinc-600">Anterior</Link>
            <Link v-if="comments.next_page_url" :href="comments.next_page_url" class="rounded-lg border border-zinc-200 px-4 py-2 text-sm dark:border-zinc-600">Próxima</Link>
        </div>
    </div>
</template>
