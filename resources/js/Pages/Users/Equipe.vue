<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/composables/useI18n';
import { Users, Shield, UserPlus, Plus, Pencil, Trash2, X, ScrollText, Trash } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const props = defineProps({
    products: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
});

const page = usePage();
const role = computed(() => page.props.auth?.user?.role);

const tabs = computed(() => {
    const base = [
        { key: 'cargos', label: t('team.roles', 'Cargos') },
        { key: 'membros', label: t('team.members', 'Membros') },
    ];
    if (role.value === 'admin') {
        base.push({ key: 'logs', label: t('team.logs', 'Logs') });
    }
    return base;
});
const activeTab = ref('cargos');

const userTabs = computed(() => {
    // Admin tem as 2 abas; infoprodutor/equipe só faz sentido Equipe.
    return [
        { key: 'usuarios', label: t('users.tab_infoproducers', 'Infoprodutores'), href: '/usuarios', adminOnly: true },
        { key: 'equipe', label: t('users.tab_team', 'Equipe'), href: '/usuarios/equipe', adminOnly: false },
    ];
});
function isUsersTabActive(href) {
    // Evitar que "/usuarios" fique ativo em "/usuarios/equipe"
    if (href === '/usuarios') {
        return page.url === '/usuarios' || page.url.startsWith('/usuarios?');
    }
    return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
}

const permissionDefs = [
    { key: 'dashboard.view', label: t('sidebar.dashboard', 'Dashboard') },
    { key: 'vendas.view', label: t('sidebar.sales', 'Vendas') },
    { key: 'financeiro.view', label: t('sidebar.finance', 'Financeiro') },
    { key: 'produtos.view', label: t('sidebar.products', 'Produtos') },
    { key: 'relatorios.view', label: t('sidebar.reports', 'Relatórios') },
    { key: 'integracoes.view', label: t('sidebar.integrations', 'Integrações') },
    { key: 'email_marketing.view', label: t('team.permission_email_marketing', 'E-mail Marketing') },
    { key: 'api_pagamentos.view', label: t('team.permission_api_payments', 'API de Pagamentos') },
    { key: 'configuracoes.view', label: t('team.permission_settings', 'Configurações') },
    { key: 'equipe.manage', label: t('team.permission_manage_team', 'Gerenciar equipe') },
];

const showRoleModal = ref(false);
const editingRole = ref(null);

const roleForm = useForm({
    name: '',
    permissions: {},
    product_ids: [],
});

function defaultPermissions() {
    const p = {};
    for (const def of permissionDefs) {
        p[def.key] = false;
    }
    p['dashboard.view'] = true;
    p['vendas.view'] = true;
    return p;
}

function openCreateRole() {
    editingRole.value = null;
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.name = '';
    roleForm.permissions = defaultPermissions();
    roleForm.product_ids = [];
    showRoleModal.value = true;
}

function openEditRole(role) {
    editingRole.value = role;
    roleForm.reset();
    roleForm.clearErrors();
    roleForm.name = role.name;
    roleForm.permissions = { ...defaultPermissions(), ...(role.permissions || {}) };
    roleForm.product_ids = Array.isArray(role.product_ids) ? [...role.product_ids] : [];
    showRoleModal.value = true;
}

function closeRoleModal() {
    showRoleModal.value = false;
}

function submitRole() {
    if (editingRole.value) {
        roleForm.put(`/usuarios/equipe/cargos/${editingRole.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeRoleModal(),
        });
        return;
    }
    roleForm.post('/usuarios/equipe/cargos', {
        preserveScroll: true,
        onSuccess: () => closeRoleModal(),
    });
}

function confirmDeleteRole(role) {
    if (!window.confirm(t('team.confirm_delete_role', `Remover o cargo "${role.name}"?`).replace('{name}', role.name))) return;
    router.delete(`/usuarios/equipe/cargos/${role.id}`, { preserveScroll: true });
}

const showMemberModal = ref(false);
const editingMember = ref(null);
const memberForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    team_role_id: null,
    send_access_email: true,
});

const roleOptions = computed(() => props.roles.map((r) => ({ id: r.id, name: r.name })));

function openCreateMember() {
    editingMember.value = null;
    memberForm.reset();
    memberForm.clearErrors();
    memberForm.team_role_id = roleOptions.value[0]?.id ?? null;
    memberForm.send_access_email = true;
    showMemberModal.value = true;
}

function openEditMember(m) {
    editingMember.value = m;
    memberForm.reset();
    memberForm.clearErrors();
    memberForm.name = m.name;
    memberForm.email = m.email;
    memberForm.password = '';
    memberForm.password_confirmation = '';
    memberForm.team_role_id = m.team_role_id ?? roleOptions.value[0]?.id ?? null;
    memberForm.send_access_email = false;
    showMemberModal.value = true;
}

function closeMemberModal() {
    showMemberModal.value = false;
}

function submitMember() {
    if (editingMember.value) {
        memberForm.put(`/usuarios/equipe/membros/${editingMember.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeMemberModal(),
        });
        return;
    }
    memberForm.post('/usuarios/equipe/membros', {
        preserveScroll: true,
        onSuccess: () => closeMemberModal(),
    });
}

function confirmDeleteMember(m) {
    if (!window.confirm(t('team.confirm_delete_member', `Remover "{name}" da equipe?`).replace('{name}', m.name))) return;
    router.delete(`/usuarios/equipe/membros/${m.id}`, { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function confirmClearLogs() {
    if (!window.confirm(t('team.confirm_clear_logs', 'Limpar todos os logs de auditoria deste tenant?'))) return;
    router.post('/usuarios/equipe/logs/clear', {}, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ t('team.title', 'Usuários') }}
                </h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('team.subtitle', 'Gerencie equipe e permissões.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="activeTab === 'cargos'" class="inline-flex items-center gap-2" @click="openCreateRole">
                    <Plus class="h-4 w-4" />
                    {{ t('team.new_role', 'Novo cargo') }}
                </Button>
                <Button v-else-if="activeTab === 'membros'" class="inline-flex items-center gap-2" @click="openCreateMember">
                    <UserPlus class="h-4 w-4" />
                    {{ t('team.new_member', 'Novo membro') }}
                </Button>
                <Button
                    v-else-if="activeTab === 'logs' && role === 'admin'"
                    variant="outline"
                    class="inline-flex items-center gap-2"
                    @click="confirmClearLogs"
                >
                    <Trash class="h-4 w-4" />
                    {{ t('team.clear_logs', 'Limpar logs') }}
                </Button>
            </div>
        </div>

        <!-- Abas Usuários (principal) + Abas Equipe (secundária) -->
        <div class="flex flex-col gap-3">
            <nav
                class="inline-flex rounded-xl bg-zinc-100/80 p-1 dark:bg-zinc-800/80"
                :aria-label="t('users.title', 'Usuários')"
            >
                <Link
                    v-for="t in userTabs"
                    :key="t.key"
                    :href="t.href"
                    v-show="!t.adminOnly || role === 'admin'"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                        isUsersTabActive(t.href)
                            ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                            : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
                    ]"
                    :aria-current="isUsersTabActive(t.href) ? 'page' : undefined"
                >
                    <Shield v-if="t.key === 'usuarios'" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <Users v-else class="h-4 w-4 shrink-0" aria-hidden="true" />
                    {{ t.label }}
                </Link>
            </nav>

            <nav
                class="inline-flex rounded-xl bg-zinc-100/80 p-1 dark:bg-zinc-800/80"
                :aria-label="t('users.tab_team', 'Equipe')"
            >
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    :class="[
                        'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                        activeTab === t.key
                            ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                            : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
                    ]"
                    :aria-current="activeTab === t.key ? 'page' : undefined"
                    @click="activeTab = t.key"
                >
                    <Shield v-if="t.key === 'cargos'" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <Users v-else-if="t.key === 'membros'" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <ScrollText v-else class="h-4 w-4 shrink-0" aria-hidden="true" />
                    {{ t.label }}
                </button>
            </nav>
        </div>

        <!-- Cargos -->
        <div v-if="activeTab === 'cargos'" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 overflow-hidden">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <li v-for="r in roles" :key="r.id" class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                            <Shield class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ r.name }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ (r.product_ids?.length ?? 0) }} {{ t('team.products_count', 'produto(s)') }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ t('team.permissions', 'Permissões') }}: {{ permissionDefs.filter(p => r.permissions?.[p.key]).map(p => p.label).join(', ') || '—' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-200 transition-colors"
                            :title="t('team.edit_role', 'Editar cargo')"
                            @click="openEditRole(r)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                            :title="t('team.remove_role', 'Remover cargo')"
                            @click="confirmDeleteRole(r)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </li>
            </ul>
            <p v-if="!roles.length" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('team.no_roles', 'Nenhum cargo criado.') }}
            </p>
        </div>

        <!-- Membros -->
        <div v-else-if="activeTab === 'membros'" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 overflow-hidden">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <li v-for="m in members" :key="m.id" class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                            <Users class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ m.name }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ m.team_role_name || t('team.no_role', 'Sem cargo') }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">{{ m.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-zinc-500 dark:text-zinc-400 tabular-nums">
                            {{ formatDate(m.created_at) }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-200 transition-colors"
                            :title="t('team.edit_member', 'Editar membro')"
                            @click="openEditMember(m)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                            :title="t('team.remove_member', 'Remover membro')"
                            @click="confirmDeleteMember(m)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </li>
            </ul>
            <p v-if="!members.length" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('team.no_members', 'Nenhum membro cadastrado.') }}
            </p>
        </div>

        <!-- Logs (admin only) -->
        <div
            v-else
            class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 overflow-hidden"
        >
            <div class="overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/70 dark:bg-zinc-900/40">
                        <tr class="text-left text-zinc-600 dark:text-zinc-300">
                            <th class="px-4 py-3 font-medium">{{ t('team.when', 'Quando') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('team.user', 'Usuário') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('team.action', 'Ação') }}</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        <tr v-for="l in logs" :key="l.id" class="text-zinc-700 dark:text-zinc-200">
                            <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(l.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ l.actor?.name || '—' }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ l.actor?.email || '' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-mono text-xs text-zinc-700 dark:text-zinc-200">
                                    {{ l.action }}
                                </div>
                                <div v-if="l.target_type || l.target_id" class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ l.target_type }} {{ l.target_id ? `#${l.target_id}` : '' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                {{ l.ip || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!logs.length" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ t('team.no_logs', 'Nenhum log registrado ainda.') }}
            </p>
        </div>
    </div>

    <!-- Modal: Cargo -->
    <Teleport to="body">
        <div
            v-if="showRoleModal"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70" aria-hidden="true" @click="closeRoleModal" />
            <div class="relative w-full max-w-2xl rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ editingRole ? t('team.edit_role', 'Editar cargo') : t('team.new_role', 'Novo cargo') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                        :aria-label="t('common.close', 'Fechar')"
                        @click="closeRoleModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <form class="space-y-5 p-5" @submit.prevent="submitRole">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ t('team.role_name', 'Nome do cargo') }}</label>
                        <input
                            v-model="roleForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="roleForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ roleForm.errors.name }}</p>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ t('team.permissions', 'Permissões') }}</h3>
                            <div class="mt-3 space-y-2">
                                <label v-for="p in permissionDefs" :key="p.key" class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                    <input v-model="roleForm.permissions[p.key]" type="checkbox" class="rounded border-zinc-300 dark:border-zinc-600" />
                                    <span>{{ p.label }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ t('team.allowed_products', 'Produtos permitidos') }}</h3>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ t('team.allowed_products_hint', 'Afeta todos os módulos por produto (Dashboard, Vendas, Produtos, etc.).') }}
                            </p>
                            <div class="mt-3 max-h-[260px] overflow-auto space-y-2 pr-1">
                                <label v-for="p in products" :key="p.id" class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                                    <input
                                        :value="p.id"
                                        v-model="roleForm.product_ids"
                                        type="checkbox"
                                        class="rounded border-zinc-300 dark:border-zinc-600"
                                    />
                                    <span class="truncate">{{ p.name }}</span>
                                </label>
                            </div>
                            <p v-if="roleForm.errors.product_ids" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ roleForm.errors.product_ids }}</p>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <Button type="submit" :disabled="roleForm.processing">
                            {{ t('common.save', 'Salvar') }}
                        </Button>
                        <Button type="button" variant="outline" @click="closeRoleModal">
                            {{ t('common.cancel', 'Cancelar') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Membro -->
    <Teleport to="body">
        <div
            v-if="showMemberModal"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70" aria-hidden="true" @click="closeMemberModal" />
            <div class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ editingMember ? t('team.edit_member', 'Editar membro') : t('team.new_member', 'Novo membro') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                        :aria-label="t('common.close', 'Fechar')"
                        @click="closeMemberModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitMember">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ t('common.name', 'Nome') }}</label>
                        <input v-model="memberForm.name" type="text" required class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100" />
                        <p v-if="memberForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ memberForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ t('common.email', 'E-mail') }}</label>
                        <input v-model="memberForm.email" type="email" required class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100" />
                        <p v-if="memberForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ memberForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ t('team.role', 'Cargo') }}</label>
                        <select v-model="memberForm.team_role_id" required class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100">
                            <option v-for="r in roleOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                        <p v-if="memberForm.errors.team_role_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ memberForm.errors.team_role_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ editingMember ? t('team.new_password_optional', 'Nova senha (opcional)') : t('common.password', 'Senha') }}
                        </label>
                        <input v-model="memberForm.password" type="password" :required="!editingMember" minlength="8" class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100" />
                        <p v-if="memberForm.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ memberForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ t('common.confirm_password', 'Confirmar senha') }}</label>
                        <input v-model="memberForm.password_confirmation" type="password" :required="!editingMember && !!memberForm.password" minlength="8" class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100" />
                    </div>
                    <div v-if="!editingMember" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/40 p-3">
                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-200">
                            <input v-model="memberForm.send_access_email" type="checkbox" class="rounded border-zinc-300 dark:border-zinc-600" />
                            <span>{{ t('team.send_access_email', 'Enviar e-mail de acesso com login e senha') }}</span>
                        </label>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ t('team.send_access_email_hint', 'O e-mail será enviado para o endereço informado acima.') }}
                        </p>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <Button type="submit" :disabled="memberForm.processing">
                            {{ t('common.save', 'Salvar') }}
                        </Button>
                        <Button type="button" variant="outline" @click="closeMemberModal">
                            {{ t('common.cancel', 'Cancelar') }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

