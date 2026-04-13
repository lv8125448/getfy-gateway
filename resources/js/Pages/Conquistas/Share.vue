<script setup>
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { formatCompactCurrency } from '@/lib/utils';
import { Copy } from 'lucide-vue-next';

defineOptions({ layout: null });

const props = defineProps({
    achievement: {
        type: Object,
        required: true,
    },
    displayUsername: { type: String, default: null },
    shareUrl: { type: String, default: '' },
});

const copied = ref(false);

function getShareUrl() {
    return typeof window !== 'undefined' ? window.location.href : props.shareUrl || '';
}

function copyLink() {
    copied.value = true;
    const url = getShareUrl();
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
}
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-emerald-950/20 via-zinc-50 to-amber-50/30 dark:from-zinc-950 dark:via-zinc-900 dark:to-emerald-950/20">
        <!-- Background pattern -->
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.03] dark:opacity-[0.04]"
            style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 40px 40px;"
            aria-hidden
        />
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-20%,var(--tw-gradient-stops))] from-emerald-400/15 to-transparent dark:from-emerald-500/10" aria-hidden />

        <div class="relative mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-6 py-16">
            <!-- Card principal -->
            <div
                class="w-full max-w-sm overflow-hidden rounded-3xl border border-zinc-200/80 bg-white/90 shadow-xl shadow-zinc-200/50 backdrop-blur-sm dark:border-zinc-700/80 dark:bg-zinc-800/95 dark:shadow-zinc-950/50"
            >
                <div class="relative px-10 pt-12 pb-10">
                    <!-- Badge com glow -->
                    <div class="relative mx-auto flex h-36 w-36 items-center justify-center">
                        <div
                            class="absolute inset-0 rounded-full bg-gradient-to-br from-emerald-400/30 to-amber-500/20 blur-2xl dark:from-emerald-500/20 dark:to-amber-600/15"
                            aria-hidden
                        />
                        <div
                            class="relative flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-zinc-100 to-zinc-50 ring-2 ring-zinc-200/60 dark:from-zinc-800 dark:to-zinc-900 dark:ring-zinc-600/40"
                        >
                            <img
                                v-if="achievement.image"
                                :src="achievement.image"
                                :alt="achievement.name"
                                class="h-20 w-20 object-contain drop-shadow-sm"
                            />
                        </div>
                    </div>

                    <!-- Valor -->
                    <p class="mt-6 text-center text-sm font-medium uppercase tracking-widest text-emerald-600 dark:text-emerald-400">
                        R$ {{ formatCompactCurrency(achievement.threshold ?? 0) }} em vendas
                    </p>

                    <!-- Nome da conquista -->
                    <h1 class="mt-2 text-center text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ achievement.name }}
                    </h1>

                    <!-- Usuário -->
                    <p v-if="displayUsername" class="mt-4 text-center text-base text-zinc-600 dark:text-zinc-300">
                        {{ displayUsername }} conquistou
                    </p>
                    <p v-else class="mt-4 text-center text-base text-zinc-500 dark:text-zinc-400">
                        Conquista desbloqueada
                    </p>

                    <!-- Brand -->
                    <p class="mt-8 text-center text-xs font-medium tracking-wider text-zinc-400 dark:text-zinc-500">
                        Getfy
                    </p>
                </div>
            </div>

            <!-- Botão copiar no final -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div v-if="!copied" class="mt-12">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-2 rounded-full px-6"
                        @click="copyLink"
                    >
                        <Copy class="h-4 w-4" />
                        Copiar link
                    </Button>
                </div>
            </Transition>
        </div>
    </div>
</template>
