<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
    redirect: { type: String, default: '' },
});

const page = usePage();
const branding = computed(() => page.props.public_branding ?? {});
const primary = computed(() => branding.value.theme_primary || '#c8fa64');
const appName = computed(() => branding.value.app_name || 'Getfy');
const logoLight = computed(() => branding.value.app_logo_icon || 'https://cdn.getfy.cloud/collapsed-logo.png');
const logoDark = computed(() => branding.value.app_logo_icon_dark || logoLight.value);

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = useForm({
    token: props.token,
    email: props.email || '',
    password: '',
    password_confirmation: '',
    redirect: props.redirect || '',
});

function submit() {
    form.post('/redefinir-senha', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <div class="wl-root flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-4 dark:bg-zinc-950">
        <div class="w-full max-w-sm space-y-6">
            <img :src="logoLight" :alt="appName" class="mx-auto h-12 w-auto object-contain dark:hidden" />
            <img :src="logoDark" :alt="appName" class="mx-auto hidden h-12 w-auto object-contain dark:block" />
            <h1 class="text-center text-2xl font-bold text-zinc-900 dark:text-white">Redefinir senha</h1>
            <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">Digite sua nova senha abaixo.</p>

            <form class="space-y-5" @submit.prevent="submit">
                <input v-model="form.token" type="hidden" name="token" />
                <input v-if="form.redirect" v-model="form.redirect" type="hidden" name="redirect" />
                <div>
                    <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        class="wl-input mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-500 shadow-sm transition dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500"
                        placeholder="seu@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nova senha</label>
                    <div class="relative mt-1.5">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            required
                            class="wl-input block w-full rounded-xl border border-zinc-300 bg-white py-3 pl-4 pr-12 text-zinc-900 placeholder-zinc-500 shadow-sm transition dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1.5 text-zinc-500 transition hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
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
                            class="wl-input block w-full rounded-xl border border-zinc-300 bg-white py-3 pl-4 pr-12 text-zinc-900 placeholder-zinc-500 shadow-sm transition dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1.5 text-zinc-500 transition hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                            :aria-label="showPasswordConfirmation ? 'Ocultar senha' : 'Mostrar senha'"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                        >
                            <Eye v-if="showPasswordConfirmation" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                    <p v-if="form.errors.password_confirmation" class="mt-1.5 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>
                <Button type="submit" class="wl-submit w-full hover:!opacity-90" :disabled="form.processing">
                    {{ form.processing ? 'Redefinindo…' : 'Redefinir senha' }}
                </Button>
            </form>

            <p class="text-center">
                <Link
                    :href="props.redirect || '/login'"
                    class="wl-link text-sm font-medium hover:underline focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-zinc-950 rounded"
                >
                    Voltar ao login
                </Link>
            </p>
        </div>
    </div>
</template>

<style scoped>
.wl-root {
    --wl-primary: v-bind(primary);
}
.wl-input:hover {
    border-color: color-mix(in srgb, var(--wl-primary) 45%, var(--tw-border-color, #d4d4d8));
}
.wl-input:focus {
    border-color: var(--wl-primary);
    outline: none;
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--wl-primary) 35%, transparent);
}
.wl-submit {
    background-color: var(--wl-primary) !important;
    color: #18181b !important;
}
.wl-link {
    color: var(--wl-primary);
}
</style>
