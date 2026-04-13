<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Package, Handshake, UserPlus } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();

const path = computed(() => page.url.split('?')[0]);

const isCoproducao = computed(() => path.value === '/produtos/coproducao');

const isAfiliados = computed(() => path.value === '/produtos/afiliados' || /^\/produtos\/[^/]+\/painel-afiliado/.test(path.value));

const isProdutos = computed(() => {
    const p = path.value;
    if (
        p === '/produtos/coproducao' ||
        p === '/produtos/afiliados' ||
        /^\/produtos\/[^/]+\/painel-afiliado/.test(p)
    ) {
        return false;
    }
    return p === '/produtos' || /^\/produtos\/[^/]+/.test(p);
});
</script>

<template>
    <nav
        class="inline-flex rounded-xl bg-zinc-100/80 p-1 dark:bg-zinc-800/80"
        :aria-label="t('sidebar.products', 'Produtos')"
    >
        <Link
            href="/produtos"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isProdutos
                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                    : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
            ]"
        >
            <Package class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ t('products.tab_products', 'Produtos') }}
        </Link>
        <Link
            href="/produtos/coproducao"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isCoproducao
                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                    : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
            ]"
        >
            <Handshake class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ t('products.tab_coproduction', 'Co-produção') }}
        </Link>
        <Link
            href="/produtos/afiliados"
            :class="[
                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                isAfiliados
                    ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                    : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
            ]"
        >
            <UserPlus class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ t('products.tab_affiliates', 'Afiliados') }}
        </Link>
    </nav>
</template>
