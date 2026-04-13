<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { UserPlus, Trash2, Shield, User, Pencil, X } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useI18n();

const userTabs = [
    { key: 'usuarios', label: t('users.tab_infoproducers', 'Infoprodutores'), href: '/usuarios' },
    { key: 'equipe', label: t('users.tab_team', 'Equipe'), href: '/usuarios/equipe' },
];
function isUsersTabActive(href) {
    // Evitar que "/usuarios" fique ativo em "/usuarios/equipe"
    if (href === '/usuarios') {
        return page.url === '/usuarios' || page.url.startsWith('/usuarios?');
    }
    return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
}

const showCreateModal = ref(false);
const editUser = ref(null);
const deletingId = ref(null);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const isCreateModalOpen = computed(() => showCreateModal.value);
const isEditModalOpen = computed(() => editUser.value !== null);

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

function openEditModal(u) {
    editUser.value = u;
    editForm.name = u.name;
    editForm.email = u.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.clearErrors();
}

function closeEditModal() {
    editUser.value = null;
}

function submitCreate() {
    createForm.post('/usuarios', {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
}

function submitEdit() {
    if (!editUser.value) return;
    editForm.put(`/usuarios/${editUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function confirmDelete(u) {
    if (u.is_master) return;
    if (!window.confirm(`Excluir "${u.name}"? Esta ação não pode ser desfeita.`)) return;
    deletingId.value = u.id;
    router.delete(`/usuarios/${u.id}`, {
        preserveScroll: true,
        onFinish: () => { deletingId.value = null; },
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    {{ t('users.title', 'Usuários') }}
                </h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('users.subtitle', 'Conta Master e infoprodutores da plataforma.') }}
                </p>
            </div>
            <Button class="inline-flex items-center gap-2" @click="openCreateModal">
                <UserPlus class="h-4 w-4" />
                {{ t('users.new_infoproducer', 'Novo infoprodutor') }}
            </Button>
        </div>

        <!-- Abas Usuários -->
        <nav
            class="inline-flex rounded-xl bg-zinc-100/80 p-1 dark:bg-zinc-800/80"
            :aria-label="t('users.title', 'Usuários')"
        >
            <Link
                v-for="t in userTabs"
                :key="t.key"
                :href="t.href"
                :class="[
                    'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                    isUsersTabActive(t.href)
                        ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                        : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
                ]"
                :aria-current="isUsersTabActive(t.href) ? 'page' : undefined"
            >
                <Shield v-if="t.key === 'usuarios'" class="h-4 w-4 shrink-0" aria-hidden="true" />
                <User v-else class="h-4 w-4 shrink-0" aria-hidden="true" />
                {{ t.label }}
            </Link>
        </nav>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/80 overflow-hidden">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                <li
                    v-for="u in users"
                    :key="u.id"
                    class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 hover:bg-zinc-100/80 dark:hover:bg-zinc-700/50 transition-colors"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <span
                            v-if="u.avatar_url"
                            class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-600"
                        >
                            <img :src="u.avatar_url" :alt="u.name" class="h-full w-full object-cover" />
                        </span>
                        <span
                            v-else
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white"
                            :class="u.is_master ? 'bg-amber-500 dark:bg-amber-600' : 'bg-zinc-400 dark:bg-zinc-600'"
                        >
                            <Shield v-if="u.is_master" class="h-5 w-5" />
                            <User v-else class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ u.name }}</span>
                                <span
                                    v-if="u.is_master"
                                    class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200"
                                >
                                    Master
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">{{ u.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-zinc-500 dark:text-zinc-400 tabular-nums">
                            {{ formatDate(u.created_at) }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 dark:hover:bg-zinc-600 dark:hover:text-zinc-200 transition-colors"
                            :title="t('users.edit_user', 'Editar usuário')"
                            @click="openEditModal(u)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            v-if="!u.is_master"
                            type="button"
                            :disabled="deletingId === u.id"
                            class="rounded-lg p-2 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 disabled:opacity-50 transition-colors"
                            :title="t('users.delete_user', 'Excluir usuário')"
                            @click="confirmDelete(u)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </li>
            </ul>
            <p
                v-if="!users.length"
                class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400"
            >
                {{ t('users.empty', 'Nenhum usuário cadastrado.') }}
            </p>
        </div>
    </div>

    <!-- Modal: Novo usuário -->
    <Teleport to="body">
        <div
            v-if="isCreateModalOpen"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-create-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="closeCreateModal"
            />
            <div
                class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 id="modal-create-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                        Novo infoprodutor
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                        aria-label="Fechar"
                        @click="closeCreateModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitCreate">
                    <div>
                        <label for="create-name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                        <input
                            id="create-name"
                            v-model="createForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="create-email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                        <input
                            id="create-email"
                            v-model="createForm.email"
                            type="email"
                            required
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="createForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.email }}</p>
                    </div>
                    <div>
                        <label for="create-password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Senha</label>
                        <input
                            id="create-password"
                            v-model="createForm.password"
                            type="password"
                            required
                            minlength="8"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="createForm.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ createForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="create-password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Confirmar senha</label>
                        <input
                            id="create-password_confirmation"
                            v-model="createForm.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                    </div>
                    <div class="flex gap-3 pt-2">
                        <Button type="submit" :disabled="createForm.processing">
                            Cadastrar
                        </Button>
                        <Button type="button" variant="outline" @click="closeCreateModal">
                            Cancelar
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Editar usuário -->
    <Teleport to="body">
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-[100002] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-edit-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/60 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="closeEditModal"
            />
            <div
                class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 id="modal-edit-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                        Editar usuário
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                        aria-label="Fechar"
                        @click="closeEditModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <form class="space-y-4 p-5" @submit.prevent="submitEdit">
                    <div>
                        <label for="edit-name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                        <input
                            id="edit-name"
                            v-model="editForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="edit-email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                        <input
                            id="edit-email"
                            v-model="editForm.email"
                            type="email"
                            required
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="editForm.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ editForm.errors.email }}</p>
                    </div>
                    <div>
                        <label for="edit-password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nova senha (deixe em branco para não alterar)</label>
                        <input
                            id="edit-password"
                            v-model="editForm.password"
                            type="password"
                            minlength="8"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                        <p v-if="editForm.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ editForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="edit-password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Confirmar nova senha</label>
                        <input
                            id="edit-password_confirmation"
                            v-model="editForm.password_confirmation"
                            type="password"
                            minlength="8"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                        />
                    </div>
                    <div class="flex gap-3 pt-2">
                        <Button type="submit" :disabled="editForm.processing">
                            Salvar
                        </Button>
                        <Button type="button" variant="outline" @click="closeEditModal">
                            Cancelar
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
