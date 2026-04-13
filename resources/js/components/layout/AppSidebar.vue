<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CircleDollarSign,
    Wallet,
    Package,
    Repeat,
    Users,
    BarChart3,
    Puzzle,
    Cable,
    Settings,
    PanelRightOpen,
    X,
    Plug,
    Wrench,
    FileCode,
    Box,
    Mail,
    CodeXml,
    Store,
    TicketPercent,
    GraduationCap,
    UserPlus,
} from 'lucide-vue-next';
import { useSidebar } from '@/composables/useSidebar';
import ConquistasWidget from '@/components/layout/ConquistasWidget.vue';
import PwaInstallButton from '@/components/layout/PwaInstallButton.vue';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { isExpanded, isMobileOpen, toggleSidebar, isMobile } = useSidebar();
const { t } = useI18n();
const hoverLabel = ref('');
const hoverX = ref(0);
const hoverY = ref(0);

const showText = () => isExpanded.value || isMobileOpen.value;

const appSettings = () => page.props.appSettings ?? {};
const appName = () => appSettings().app_name || 'Infoprodutor';
const hasLogoFull = () => !!(appSettings().app_logo || appSettings().app_logo_dark);
const hasLogoIcon = () => !!(appSettings().app_logo_icon || appSettings().app_logo_icon_dark);

const iconMap = {
    Puzzle,
    Plug,
    Wrench,
    Settings,
    FileCode,
    Box,
    LayoutDashboard,
    Package,
    Repeat,
    Users,
    BarChart3,
    Mail,
    CodeXml,
};

const pluginNavItems = computed(() => {
    const raw = page.props.pluginNavItems ?? [];
    return raw.map((item) => ({
        name: item.name,
        href: item.href,
        icon: item.icon && iconMap[item.icon] ? iconMap[item.icon] : Puzzle,
    }));
});

const perms = computed(() => page.props.auth?.permissions ?? {});
const canView = (key) => {
    const role = page.props.auth?.user?.role;
    if (role === 'admin' || role === 'infoprodutor') return true;
    return !!perms.value?.[key];
};

const navItems = computed(() => {
    const items = [];

    // Core items com permissões (usuário de equipe)
    if (canView('dashboard.view')) items.push({ name: t('sidebar.dashboard', 'Dashboard'), href: '/dashboard', icon: LayoutDashboard });
    if (canView('vendas.view')) items.push({ name: t('sidebar.sales', 'Vendas'), href: '/vendas', icon: CircleDollarSign });
    if (canView('produtos.view')) {
        items.push({ name: t('sidebar.products', 'Produtos'), href: '/produtos', icon: Package });
        items.push({
            name: t('sidebar.affiliate_showcase', 'Vitrine'),
            href: '/produtos/vitrine-afiliacao',
            icon: Store,
        });
        items.push({ name: t('sidebar.coupons', 'Cupons'), href: '/produtos/cupons', icon: TicketPercent });
        items.push({ name: t('sidebar.students', 'Alunos'), href: '/produtos/alunos', icon: GraduationCap });
        items.push({ name: t('sidebar.affiliates_menu', 'Afiliados'), href: '/afiliados', icon: UserPlus });
    }
    if (canView('relatorios.view')) items.push({ name: t('sidebar.reports', 'Relatórios'), href: '/relatorios', icon: BarChart3 });
    if (canView('integracoes.view')) items.push({ name: t('sidebar.integrations', 'Integrações'), href: '/integracoes', icon: Cable });

    // Plugins: apenas admin/infoprodutor (backend reforça) — gestão global fica em /plataforma
    if ((page.props.auth?.user?.role === 'admin' || page.props.auth?.user?.role === 'infoprodutor') && pluginNavItems.value.length) {
        items.push(...pluginNavItems.value);
    }

    // Equipe: dono (infoprodutor) ou permissão de equipe — gestão de infoprodutores fica em /plataforma (operador).
    if (page.props.auth?.user?.role === 'infoprodutor' || canView('equipe.manage')) {
        items.push({ name: t('sidebar.team', 'Equipe'), href: '/usuarios/equipe', icon: Users });
    }
    if (canView('financeiro.view')) items.push({ name: t('sidebar.finance', 'Financeiro'), href: '/financeiro', icon: Wallet });

    items.push({ separator: true });
    return items;
});

function isActive(href) {
    const url = page.url.split('?')[0];
    if (href === '/dashboard') return url === '/dashboard' || url === '/';
    if (href === '/produtos/vitrine-afiliacao') {
        return url === '/produtos/vitrine-afiliacao' || url.startsWith('/produtos/vitrine-afiliacao/');
    }
    if (href === '/produtos/cupons') {
        return url.startsWith('/produtos/cupons');
    }
    if (href === '/produtos/alunos') {
        return url.startsWith('/produtos/alunos');
    }
    if (href === '/afiliados') {
        return url === '/afiliados' || url.startsWith('/afiliados/');
    }
    if (href === '/produtos') {
        if (
            url.startsWith('/produtos/cupons') ||
            url.startsWith('/produtos/alunos') ||
            url.startsWith('/produtos/coproducao') ||
            url.startsWith('/produtos/vitrine-afiliacao') ||
            url.startsWith('/produtos/afiliados') ||
            /\/painel-afiliado/.test(url)
        ) {
            return false;
        }
        return url === '/produtos' || url.startsWith('/produtos/');
    }
    return url === href || url.startsWith(href + '/');
}

/** Prefetch hover + mousedown para navegação do painel (Inertia v2). */
const panelNavPrefetch = ['hover', 'click'];

function onItemMouseEnter(event, label) {
    if (showText() || isMobile.value) return;
    hoverLabel.value = label;
    hoverX.value = event.clientX + 14;
    hoverY.value = event.clientY;
}

function onItemMouseMove(event) {
    if (!hoverLabel.value) return;
    hoverX.value = event.clientX + 14;
    hoverY.value = event.clientY;
}

function onItemMouseLeave() {
    hoverLabel.value = '';
}
</script>

<template>
    <aside
        :class="[
            'fixed left-0 top-0 z-[99999] flex h-screen flex-col rounded-r-2xl bg-zinc-100 transition-all duration-300 ease-in-out dark:bg-zinc-900',
            {
                'w-[260px] translate-x-0': isMobileOpen,
                '-translate-x-full': !isMobileOpen,
                'lg:translate-x-0': true,
                'lg:w-[260px]': isExpanded || isMobileOpen,
                'lg:w-[72px]': !isExpanded && !isMobileOpen,
            },
        ]"
    >
        <div
            :class="[
                'flex items-center border-b border-zinc-200/60 px-4 py-5 dark:border-zinc-700/60',
                showText() ? 'justify-between gap-2' : 'lg:justify-center',
            ]"
        >
            <!-- Expandido: logo + botão recolher -->
            <template v-if="showText()">
                <Link
                    href="/dashboard"
                    :prefetch="panelNavPrefetch"
                    class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden text-zinc-900 dark:text-white"
                >
                    <template v-if="hasLogoFull()">
                        <img v-if="appSettings().app_logo" :src="appSettings().app_logo" :alt="appName()" class="h-10 max-w-[200px] object-contain object-left" :class="appSettings().app_logo_dark ? 'dark:hidden' : ''" />
                        <img v-if="appSettings().app_logo_dark" :src="appSettings().app_logo_dark" :alt="appName()" class="hidden h-10 max-w-[200px] object-contain object-left dark:block" />
                    </template>
                    <span v-else class="truncate text-lg font-semibold">{{ appName() }}</span>
                </Link>
                <button
                    type="button"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                    :aria-label="isMobile ? 'Fechar menu' : 'Recolher menu'"
                    @click="toggleSidebar"
                >
                    <X v-if="isMobile" class="h-5 w-5" aria-hidden="true" />
                    <PanelRightOpen v-else class="h-5 w-5" aria-hidden="true" />
                </button>
            </template>
            <!-- Recolhido: só logo (clique abre) -->
            <button
                v-else
                type="button"
                class="flex h-14 w-14 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                aria-label="Expandir menu"
                @click="toggleSidebar"
            >
                <template v-if="hasLogoIcon()">
                    <img v-if="appSettings().app_logo_icon" :src="appSettings().app_logo_icon" :alt="appName()" class="h-12 w-12 object-contain" :class="appSettings().app_logo_icon_dark ? 'dark:hidden' : ''" />
                    <img v-if="appSettings().app_logo_icon_dark" :src="appSettings().app_logo_icon_dark" :alt="appName()" class="hidden h-12 w-12 object-contain dark:block" />
                </template>
                <span v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-zinc-200 text-lg font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                    {{ appName().charAt(0) }}
                </span>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto overflow-x-visible no-scrollbar px-3 py-4">
            <ul class="flex flex-col gap-1 overflow-visible">
                <template v-for="(item, index) in navItems" :key="item.separator ? `sep-${index}` : (item.href ?? index)">
                    <li v-if="item.separator">
                        <hr class="my-2 border-t border-zinc-200 dark:border-zinc-700" />
                    </li>
                    <li v-else class="overflow-visible">
                        <Link
                            :href="item.href"
                            :prefetch="panelNavPrefetch"
                            :title="showText() ? '' : item.name"
                            @mouseenter="(e) => onItemMouseEnter(e, item.name)"
                            @mousemove="onItemMouseMove"
                            @mouseleave="onItemMouseLeave"
                            :class="[
                                'menu-item group relative',
                                showText() ? 'justify-start' : 'lg:justify-center',
                                isActive(item.href) ? 'menu-item-active' : 'menu-item-inactive',
                            ]"
                        >
                            <span
                                :class="[
                                    'shrink-0',
                                    isActive(item.href) ? 'menu-item-icon-active' : 'menu-item-icon-inactive',
                                ]"
                            >
                                <component :is="item.icon" class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span
                                v-if="showText()"
                                class="truncate"
                            >
                                {{ item.name }}
                            </span>
                            <span
                                v-else
                                class="hidden"
                            >
                                {{ item.name }}
                            </span>
                        </Link>
                    </li>
                </template>
            </ul>
        </nav>
        <!-- Mobile: Instalar App + Conquistas (parte inferior) -->
        <div v-if="isMobile && showText()" class="space-y-2 px-4 py-4 lg:hidden">
            <PwaInstallButton />
            <ConquistasWidget variant="sidebar" />
        </div>
    </aside>
    <div
        v-if="hoverLabel"
        class="pointer-events-none fixed z-[100001] hidden -translate-y-1/2 whitespace-nowrap rounded-md bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white shadow-lg dark:bg-zinc-100 dark:text-zinc-900 lg:block"
        :style="{ left: `${hoverX}px`, top: `${hoverY}px` }"
    >
        {{ hoverLabel }}
    </div>
</template>
