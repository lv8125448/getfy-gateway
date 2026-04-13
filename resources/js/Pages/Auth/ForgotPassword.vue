<script setup>
import { computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';

const page = usePage();
const status = computed(() => page.props.flash?.status ?? null);
const branding = computed(() => page.props.public_branding ?? {});
const primary = computed(() => branding.value.theme_primary || '#c8fa64');
const appName = computed(() => branding.value.app_name || 'Getfy');
const logoLight = computed(() => branding.value.app_logo_icon || 'https://cdn.getfy.cloud/collapsed-logo.png');
const logoDark = computed(() => branding.value.app_logo_icon_dark || logoLight.value);

const form = useForm({
    email: '',
});

function submit() {
    form.post('/esqueci-senha', {
        preserveScroll: true,
        onFinish: () => form.reset('email'),
    });
}
</script>

<template>
    <div class="wl-root flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-4 dark:bg-zinc-950">
        <div class="w-full max-w-sm space-y-6">
            <img :src="logoLight" :alt="appName" class="mx-auto h-12 w-auto object-contain dark:hidden" />
            <img :src="logoDark" :alt="appName" class="mx-auto hidden h-12 w-auto object-contain dark:block" />
            <h1 class="text-center text-2xl font-bold text-zinc-900 dark:text-white">Recuperar senha</h1>
            <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                Informe seu e-mail para receber o link de redefinição.
            </p>

            <div
                v-if="status"
                class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
            >
                {{ status }}
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">E-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="wl-input mt-1.5 block w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 placeholder-zinc-500 shadow-sm transition dark:border-zinc-600 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500"
                        placeholder="seu@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
                </div>
                <Button type="submit" class="wl-submit w-full hover:!opacity-90" :disabled="form.processing">
                    {{ form.processing ? 'Enviando…' : 'Enviar link' }}
                </Button>
            </form>

            <p class="text-center">
                <Link
                    href="/login"
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
