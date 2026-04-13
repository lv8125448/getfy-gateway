<script setup>
import { ref, onMounted } from 'vue';

const theme = ref('light');

function setTheme(value) {
    theme.value = value;
    document.documentElement.classList.toggle('dark', value === 'dark');
    try {
        localStorage.setItem('theme', value);
    } catch (_) {}
}

onMounted(() => {
    const stored = localStorage.getItem('theme');
    const initial = stored || 'dark';
    theme.value = initial;
    document.documentElement.classList.toggle('dark', initial === 'dark');
});
</script>

<template>
    <div
        role="group"
        aria-label="Tema"
        class="flex rounded-full bg-zinc-200/70 p-0.5 dark:bg-zinc-600/50"
    >
        <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-full text-zinc-600 transition-all dark:text-zinc-400"
            :class="theme === 'light' ? 'bg-white text-amber-600 shadow-sm dark:shadow-none' : 'hover:text-zinc-900 dark:hover:text-zinc-200'"
            aria-label="Tema claro"
            @click="setTheme('light')"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                    fill="currentColor"
                />
            </svg>
        </button>
        <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-full text-zinc-600 transition-all dark:text-zinc-400"
            :class="theme === 'dark' ? 'bg-zinc-700 text-zinc-100 shadow-sm' : 'hover:text-zinc-900 dark:hover:text-zinc-200'"
            aria-label="Tema escuro"
            @click="setTheme('dark')"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path
                    d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"
                    fill="currentColor"
                />
            </svg>
        </button>
    </div>
</template>
