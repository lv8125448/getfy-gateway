<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps({
    showBack: { type: Boolean, default: true },
});
</script>

<template>
    <div class="min-h-screen bg-zinc-950 text-zinc-100">
        <header class="sticky top-0 z-20 border-b border-white/5 bg-zinc-950/90 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-[1600px] items-center justify-between gap-4 px-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        v-if="showBack"
                        href="/aplicacoes-api"
                        class="flex shrink-0 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-zinc-400 transition hover:border-teal-500/30 hover:bg-white/10 hover:text-white"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        <span class="hidden sm:inline">Voltar ao painel</span>
                    </Link>
                    <span class="hidden text-zinc-600 sm:inline">|</span>
                    <h1 class="truncate text-sm font-medium tracking-tight text-zinc-400 sm:text-base">
                        Documentação da API de pagamentos
                    </h1>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-[1600px] px-4 py-0 sm:px-6">
            <slot />
        </main>
    </div>
</template>
