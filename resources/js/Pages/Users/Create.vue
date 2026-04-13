<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: LayoutInfoprodutor });

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                Novo infoprodutor
            </h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Cadastre um novo usuário com acesso ao painel como infoprodutor.
            </p>
        </div>

        <form class="max-w-xl space-y-4" @submit.prevent="form.post('/usuarios')">
            <div>
                <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Senha</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    minlength="8"
                    class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Confirmar senha</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    minlength="8"
                    class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-zinc-100"
                />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    Cadastrar
                </Button>
                <Link
                    href="/usuarios"
                    class="inline-flex items-center rounded-lg border border-zinc-300 dark:border-zinc-600 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
