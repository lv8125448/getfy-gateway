<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, CircleDollarSign, Package, Cable } from 'lucide-vue-next';
import { usePwaInstall } from '@/composables/usePwaInstall';

const page = usePage();
const { isStandalone } = usePwaInstall('painel');
const appSettings = () => page.props.appSettings ?? {};
const logoUrl = () => appSettings().app_logo_icon ?? 'https://cdn.getfy.cloud/collapsed-logo.png';

const navItems = [
    { name: 'Home', href: '/dashboard', icon: LayoutDashboard },
    { name: 'Vendas', href: '/vendas', icon: CircleDollarSign },
    { name: 'Produtos', href: '/produtos', icon: Package },
    { name: 'Integrações', href: '/integracoes', icon: Cable },
];

const navVisible = ref(true);
const lastScrollY = ref(0);
const SCROLL_THRESHOLD = 20;
const TOP_THRESHOLD = 80;

function isActive(href) {
    const url = page.url;
    if (href === '/dashboard') return url === '/dashboard' || url === '/';
    return url === href || url.startsWith(href + '/');
}

const panelNavPrefetch = ['hover', 'click'];

function onScroll() {
    if (typeof window === 'undefined') return;
    const y = window.scrollY ?? window.pageYOffset;
    if (y <= TOP_THRESHOLD) {
        navVisible.value = true;
    } else if (y > lastScrollY.value && y - lastScrollY.value > SCROLL_THRESHOLD) {
        navVisible.value = false;
        lastScrollY.value = y;
    } else if (y < lastScrollY.value && lastScrollY.value - y > SCROLL_THRESHOLD) {
        navVisible.value = true;
        lastScrollY.value = y;
    }
    lastScrollY.value = y;
}

onMounted(() => {
    lastScrollY.value = typeof window !== 'undefined' ? (window.scrollY ?? window.pageYOffset) : 0;
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    if (typeof window !== 'undefined') window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <nav
        v-if="isStandalone"
        class="fixed bottom-4 left-4 right-4 z-[99998] mx-auto flex max-w-md items-center justify-between gap-1 rounded-2xl bg-zinc-900 px-3 py-2 shadow-lg dark:bg-zinc-950 lg:hidden transition-transform duration-300 ease-out"
        aria-label="Navegação principal"
        role="navigation"
        :style="{ transform: navVisible ? 'translateY(0)' : 'translateY(calc(100% + 2rem))' }"
    >
        <!-- Home e Vendas -->
        <Link
            v-for="item in navItems.slice(0, 2)"
            :key="item.href"
            :href="item.href"
            :prefetch="panelNavPrefetch"
            :aria-current="isActive(item.href) ? 'page' : undefined"
            :aria-label="item.name"
            class="flex flex-1 flex-col items-center gap-0.5 px-2 py-2 text-xs font-medium rounded-xl transition-colors cursor-pointer touch-manipulation border-0 bg-transparent text-left no-underline"
            :class="
                isActive(item.href)
                    ? 'text-[var(--color-primary)]'
                    : 'text-zinc-400 hover:text-zinc-200'
            "
        >
            <component :is="item.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
            <span>{{ item.name }}</span>
        </Link>

        <!-- Logo central -->
        <Link
            href="/dashboard"
            :prefetch="panelNavPrefetch"
            aria-label="Home"
            class="flex shrink-0 flex-col items-center justify-center -mt-6 cursor-pointer touch-manipulation border-0 bg-transparent no-underline"
        >
            <span
                class="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-700 shadow-lg shadow-black/30 ring-2 ring-zinc-500/60 dark:bg-zinc-600 dark:shadow-black/50 dark:ring-zinc-400/40"
            >
                <img
                    :src="logoUrl()"
                    alt=""
                    class="h-8 w-8 object-contain"
                    aria-hidden="true"
                />
            </span>
        </Link>

        <!-- Produtos e Ajustes -->
        <Link
            v-for="item in navItems.slice(2)"
            :key="item.href"
            :href="item.href"
            :prefetch="panelNavPrefetch"
            :aria-current="isActive(item.href) ? 'page' : undefined"
            :aria-label="item.name"
            class="flex flex-1 flex-col items-center gap-0.5 px-2 py-2 text-xs font-medium rounded-xl transition-colors cursor-pointer touch-manipulation border-0 bg-transparent text-left no-underline"
            :class="
                isActive(item.href)
                    ? 'text-[var(--color-primary)]'
                    : 'text-zinc-400 hover:text-zinc-200'
            "
        >
            <component :is="item.icon" class="h-5 w-5 shrink-0" aria-hidden="true" />
            <span>{{ item.name }}</span>
        </Link>
    </nav>
</template>
