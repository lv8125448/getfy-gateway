<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { PanelsTopLeft } from 'lucide-vue-next';
import { useSidebar } from '@/composables/useSidebar';
import ThemeToggler from '@/components/layout/ThemeToggler.vue';
import UserMenu from '@/components/layout/UserMenu.vue';

const page = usePage();
const pageTitle = computed(() => page.props.pageTitle ?? null);
const urlPath = computed(() => page.url.split('?')[0] || '');
const isPlatformArea = computed(() => urlPath.value.startsWith('/plataforma'));

const { toggleSidebar, isMobileOpen, isMobile } = useSidebar();

/** Atalhos no topo para rotas frequentes (reforço além do sidebar). */
const quickLinks = [
    { label: 'Clientes', href: '/plataforma/clientes', match: (u) => u === '/plataforma/clientes' || u.startsWith('/plataforma/clientes/') },
    { label: 'Transações', href: '/plataforma/transacoes', match: (u) => u === '/plataforma/transacoes' || u.startsWith('/plataforma/transacoes/') },
    { label: 'Infoprodutores', href: '/plataforma/usuarios', match: (u) => u === '/plataforma/usuarios' || u.startsWith('/plataforma/usuarios/') },
];
</script>

<template>
    <header class="z-[99998] flex shrink-0 w-full flex-col gap-2 bg-transparent px-4 py-3 lg:px-6 lg:py-4">
        <div class="flex w-full items-center justify-between gap-4">
        <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
            <button
                v-if="isMobile && !isMobileOpen"
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                aria-label="Abrir menu"
                @click="toggleSidebar"
            >
                <PanelsTopLeft class="h-5 w-5" aria-hidden="true" />
            </button>
            <h1 v-if="pageTitle" class="min-w-0 truncate text-xl font-semibold text-zinc-900 dark:text-white md:text-2xl">
                {{ pageTitle }}
            </h1>
            <nav
                v-if="isPlatformArea"
                class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 overflow-x-auto text-sm"
                aria-label="Atalhos da plataforma"
            >
                <span v-if="pageTitle" class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">|</span>
                <Link
                    v-for="item in quickLinks"
                    :key="item.href"
                    :href="item.href"
                    class="whitespace-nowrap font-medium transition-colors"
                    :class="
                        item.match(urlPath)
                            ? 'text-[var(--color-primary)]'
                            : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <ThemeToggler />
            <UserMenu />
        </div>
        </div>
    </header>
</template>
