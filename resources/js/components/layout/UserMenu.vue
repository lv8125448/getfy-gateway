<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const dropdownOpen = ref(false);

const panelNavPrefetch = ['hover', 'click'];
const dropdownRef = ref(null);

const user = computed(() => page.props.auth?.user ?? null);
const isPlatformAdmin = computed(() => !!page.props.auth?.is_platform_admin);
const customerPanel = computed(() => !!page.props.customer_panel);
const panelSwitch = computed(() => user.value?.panel_switch ?? {});

/** Compras/aluno: mostrar no painel do vendedor mesmo se panel_switch vier incompleto do backend. */
const showPainelAluno = computed(() => {
    if (!user.value || isPlatformAdmin.value) return false;
    if (customerPanel.value) return false;
    if (panelSwitch.value?.customer) return true;
    const r = user.value.role;
    return r === 'infoprodutor' || r === 'team';
});

/** Voltar ao painel do vendedor quando está em /painel-cliente ou /area-membros. */
const showPainelInfoprodutor = computed(() => {
    if (!user.value || isPlatformAdmin.value) return false;
    if (!customerPanel.value) return false;
    if (panelSwitch.value?.seller) return true;
    const r = user.value.role;
    return r === 'infoprodutor' || r === 'team';
});

function switchToCustomer() {
    router.post('/painel/trocar', { to: 'customer' });
}

function switchToSeller() {
    router.post('/painel/trocar', { to: 'seller' });
}

const initials = computed(() => {
    if (!user.value?.name) return '?';
    const parts = user.value.name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return (parts[0][0] || '?').toUpperCase();
});

function toggleDropdown() {
    dropdownOpen.value = !dropdownOpen.value;
}

function closeDropdown() {
    dropdownOpen.value = false;
}

function handleClickOutside(event) {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div v-if="user" ref="dropdownRef" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition-colors hover:bg-zinc-200/60 dark:hover:bg-zinc-700/50"
            @click.prevent="toggleDropdown"
        >
            <span
                class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--color-primary)] text-xs font-medium text-white"
            >
                <img
                    v-if="user.avatar_url"
                    :src="user.avatar_url"
                    :alt="user.name"
                    class="h-full w-full object-cover"
                />
                <span v-else>{{ initials }}</span>
            </span>
            <span class="hidden max-w-[120px] truncate font-medium text-zinc-700 dark:text-zinc-300 sm:block">
                {{ user.name }}
            </span>
            <svg
                class="h-4 w-4 shrink-0 text-zinc-500 transition-transform dark:text-zinc-400"
                :class="{ 'rotate-180': dropdownOpen }"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-if="dropdownOpen"
            class="absolute right-0 z-50 mt-2 w-[min(100vw-2rem,18rem)] flex flex-col rounded-xl border border-zinc-200 bg-white p-3 shadow-[var(--shadow-theme-sm)] dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
                <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ user.name }}
                </p>
                <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                    {{ user.email }}
                </p>
            </div>
            <button
                v-if="showPainelAluno"
                type="button"
                class="mt-2 flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                @click="switchToCustomer(); closeDropdown()"
            >
                <span>Painel do aluno</span>
                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">Minhas compras e área de membros</span>
            </button>
            <button
                v-if="showPainelInfoprodutor"
                type="button"
                class="mt-2 flex w-full flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                @click="switchToSeller(); closeDropdown()"
            >
                <span>Painel do infoprodutor</span>
                <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">Dashboard, vendas e produtos</span>
            </button>
            <Link
                v-if="!isPlatformAdmin && (user.role === 'infoprodutor' || user.role === 'admin' || user.role === 'team')"
                href="/meu-perfil"
                :prefetch="panelNavPrefetch"
                class="mt-2 flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                @click="closeDropdown"
            >
                Meu perfil
            </Link>
            <Link
                v-if="isPlatformAdmin"
                href="/plataforma/logout"
                method="post"
                as="button"
                class="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                @click="closeDropdown"
            >
                Sair
            </Link>
            <Link
                v-else
                href="/logout"
                method="post"
                as="button"
                class="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                @click="closeDropdown"
            >
                Sair
            </Link>
        </div>
    </div>
</template>
