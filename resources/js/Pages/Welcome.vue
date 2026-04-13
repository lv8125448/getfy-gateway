<script setup>
import LayoutGuest from '@/Layouts/LayoutGuest.vue';
</script>

<template>
    <LayoutGuest>
        <div class="max-w-2xl mx-auto text-center space-y-6">
            <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">
                Plataforma Infoprodutor
            </h1>
            <p class="text-zinc-600 dark:text-zinc-400">
                Checkout, área de membros e gestão de produtos digitais.
            </p>
            <div class="flex gap-4 justify-center flex-wrap">
                <a
                    href="/login"
                    class="inline-flex items-center px-4 py-2 rounded-lg bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 font-medium hover:opacity-90 transition"
                >
                    Entrar
                </a>
            </div>
        </div>
    </LayoutGuest>
</template>
