<script setup>
import { useForm } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { formatCompactCurrency } from '@/lib/utils';
import { Share2, Loader2 } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    progress: {
        type: Object,
        required: true,
        default: () => ({}),
    },
    username: { type: String, default: null },
});

function getShareUrl(slug) {
    const base = window.location.origin + `/conquistas/${slug}/share`;
    const u = props.username ? props.username.replace(/^@/, '') : '';
    return u ? `${base}?u=${encodeURIComponent(u)}` : base;
}

function shareAchievement(slug) {
    const url = getShareUrl(slug);
    try {
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(url).catch(() => {});
        } else {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
    } catch (_) {}
    window.open(url, '_blank', 'noopener');
}

const usernameForm = useForm({
    username: props.username ?? '',
});

function saveUsername() {
    usernameForm.put('/meu-perfil/username', { preserveScroll: true });
}
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Conquistas
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Sua evolução baseada em vendas processadas por gateways de pagamento.
            </p>
        </div>

        <!-- Resumo -->
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Vendas válidas</p>
                        <p class="text-2xl font-semibold text-zinc-900 dark:text-white">
                            R$ {{ formatCompactCurrency(progress.total_valid_sales ?? 0) }}
                        </p>
                    </div>
                    <div v-if="progress.next_achievement" class="w-full sm:max-w-xs">
                        <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span>Progresso</span>
                            <span>{{ progress.progress_percent ?? 0 }}%</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div
                                class="h-full rounded-full bg-[var(--color-primary)] transition-all duration-500"
                                :style="{ width: `${progress.progress_percent ?? 0}%` }"
                            />
                        </div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Próximo: {{ progress.next_achievement?.name }} (R$ {{ formatCompactCurrency(progress.next_achievement?.threshold ?? 0) }})
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Username para compartilhar -->
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Nome para compartilhar</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Configure um @ para aparecer nas imagens de conquistas compartilhadas.
                </p>
            </div>
            <form class="flex flex-col gap-4 p-6 sm:flex-row sm:items-end" @submit.prevent="saveUsername">
                <div class="min-w-0 flex-1">
                    <label for="username" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">@username</label>
                    <input
                        id="username"
                        v-model="usernameForm.username"
                        type="text"
                        placeholder="meunome"
                        maxlength="64"
                        class="mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-zinc-900 placeholder-zinc-400 shadow-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                    />
                    <p v-if="usernameForm.errors.username" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ usernameForm.errors.username }}
                    </p>
                </div>
                <Button type="submit" :disabled="usernameForm.processing">
                    <Loader2 v-if="usernameForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Salvar
                </Button>
            </form>
        </div>

        <!-- Grid de conquistas -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="a in (progress.achievements ?? [])"
                :key="a.slug"
                class="overflow-hidden rounded-2xl border bg-white shadow-sm transition-colors dark:bg-zinc-800/50"
                :class="a.unlocked
                    ? 'border-zinc-200 dark:border-zinc-700'
                    : 'border-zinc-100 dark:border-zinc-800 opacity-80'"
            >
                <div class="flex flex-col items-center p-6 text-center">
                    <div
                        class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-zinc-100 dark:bg-zinc-800"
                        :class="{ 'opacity-60 grayscale': !a.unlocked }"
                    >
                        <img
                            v-if="a.image"
                            :src="a.image"
                            :alt="a.name"
                            class="h-14 w-14 object-contain"
                        />
                    </div>
                    <h3 class="mt-3 font-semibold text-zinc-900 dark:text-white">{{ a.name }}</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <template v-if="a.unlocked">
                            Desbloqueado
                        </template>
                        <template v-else>
                            R$ {{ formatCompactCurrency(a.threshold) }} em vendas válidas
                        </template>
                    </p>
                    <Button
                        v-if="a.unlocked"
                        variant="outline"
                        size="sm"
                        class="mt-4"
                        @click="shareAchievement(a.slug)"
                    >
                        <Share2 class="mr-2 h-4 w-4" />
                        Compartilhar
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
