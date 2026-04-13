<script setup>
/**
 * Layout em tela cheia para o Member Builder.
 * Header fixo no topo (abas + botão fechar), conteúdo abaixo.
 */
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-zinc-100 dark:bg-zinc-950">
        <header class="flex h-14 shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-4 dark:border-zinc-800 dark:bg-zinc-900">
            <slot name="tabs" />
            <slot name="actions" />
        </header>
        <main class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="flex min-h-0 flex-1 flex-col">
                <slot />
            </div>
        </main>
    </div>
</template>
