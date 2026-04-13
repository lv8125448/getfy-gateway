<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/criar-admin', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <div class="flex min-h-screen">
        <div class="flex w-full flex-col justify-center px-8 py-12 lg:w-[30%] lg:min-w-[360px]">
            <div class="text-center">
                <img
                    src="https://cdn.getfy.cloud/collapsed-logo.png"
                    alt="Getfy"
                    class="mx-auto mb-10 h-12 w-auto object-contain"
                />
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Criar administrador</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Configure o primeiro usuário da plataforma</p>
            </div>

            <form class="mt-8 space-y-5" @submit.prevent="submit">
                <div>
                    <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        required
                        class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3 text-zinc-900 dark:text-white placeholder-zinc-500 shadow-sm transition hover:border-[#c8fa64] focus:border-[#c8fa64] focus:outline-none focus:ring-2 focus:ring-[#c8fa64]/30"
                        placeholder="Seu nome"
                    />
                    <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3 text-zinc-900 dark:text-white placeholder-zinc-500 shadow-sm transition hover:border-[#c8fa64] focus:border-[#c8fa64] focus:outline-none focus:ring-2 focus:ring-[#c8fa64]/30"
                        placeholder="seu@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Senha</label>
                    <div class="relative mt-1.5">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            class="block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 py-3 pl-4 pr-12 text-zinc-900 dark:text-white placeholder-zinc-500 shadow-sm transition hover:border-[#c8fa64] focus:border-[#c8fa64] focus:outline-none focus:ring-2 focus:ring-[#c8fa64]/30"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1.5 text-zinc-500 transition hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-[#c8fa64]/30"
                            :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="showPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Confirmar senha</label>
                    <div class="relative mt-1.5">
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            class="block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 py-3 pl-4 pr-12 text-zinc-900 dark:text-white placeholder-zinc-500 shadow-sm transition hover:border-[#c8fa64] focus:border-[#c8fa64] focus:outline-none focus:ring-2 focus:ring-[#c8fa64]/30"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1.5 text-zinc-500 transition hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-[#c8fa64]/30"
                            :aria-label="showPasswordConfirmation ? 'Ocultar senha' : 'Mostrar senha'"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                        >
                            <Eye v-if="showPasswordConfirmation" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>
                <Button type="submit" class="w-full !bg-[#c8fa64] !text-zinc-900 hover:!opacity-90" :disabled="form.processing">
                    {{ form.processing ? 'Criando…' : 'Criar e entrar' }}
                </Button>
            </form>
        </div>
        <div
            class="hidden bg-zinc-100 dark:bg-zinc-900 lg:block lg:flex-1"
            style="background-image: linear-gradient(135deg, rgba(200, 250, 100, 0.08) 0%, transparent 60%);"
            aria-hidden="true"
        />
    </div>
</template>
