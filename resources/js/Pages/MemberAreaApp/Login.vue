<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { Eye, EyeOff, Smartphone } from 'lucide-vue-next';
import PwaInstallPrompt from '@/components/member-area/PwaInstallPrompt.vue';
import { usePwaInstall } from '@/composables/usePwaInstall';

const props = defineProps({
    slug: { type: String, required: true },
    product: { type: Object, required: true },
});

const { canShowInstallButton, triggerInstall } = usePwaInstall(props.slug);

const showPassword = ref(false);

const manifestUrl = computed(() => {
    if (typeof window === 'undefined') return null;
    return `${window.location.origin}/m/${props.slug}/manifest.json`;
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const backgroundStyle = () => {
    if (props.product.background_image) {
        return { backgroundImage: `url(${props.product.background_image})` };
    }
    return { backgroundColor: props.product.background_color || '#18181b' };
};
</script>

<template>
    <Head>
        <title>{{ product.title || product.name || 'Área de Membros' }}</title>
        <link v-if="manifestUrl" rel="manifest" :href="manifestUrl" />
        <meta name="theme-color" :content="product.primary_color || '#0ea5e9'" />
        <meta name="mobile-web-app-capable" content="yes" />
    </Head>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-cover bg-center px-4 py-12 transition-colors"
        :style="{
            '--ma-primary': product.primary_color || '#0ea5e9',
            ...backgroundStyle(),
        }"
    >
        <div v-if="product.background_image" class="absolute inset-0 bg-black/50" aria-hidden="true" />
        <div
            class="relative z-10 w-full max-w-md rounded-2xl border border-white/10 bg-zinc-900/90 p-8 shadow-2xl backdrop-blur-sm"
        >
            <div class="flex flex-col items-center text-center">
                <img
                    v-if="product.logo_light"
                    :src="product.logo_light"
                    :alt="product.title || product.name"
                    class="mb-6 h-12 w-auto max-w-[200px] object-contain object-center"
                />
                <h1 class="text-2xl font-bold tracking-tight text-white">
                    {{ product.title || product.name || 'Área de Membros' }}
                </h1>
                <p class="mt-1.5 text-sm text-zinc-400">
                    {{ product.subtitle || (product.login_without_password ? 'Entre com seu e-mail' : 'Entre com seu e-mail e senha') }}
                </p>
            </div>
            <form
                class="mt-8 space-y-5"
                @submit.prevent="
                    product.login_without_password
                        ? form.post(product.login_without_password_url || `/m/${slug}/login-without-password`)
                        : form.post(`/m/${slug}/login`)
                "
            >
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-zinc-300">E-mail</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full rounded-xl border border-zinc-600 bg-zinc-800/80 px-4 py-3 text-white placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/30"
                        placeholder="seu@email.com"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-400">{{ form.errors.email }}</p>
                </div>
                <div v-if="!product.login_without_password">
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="text-sm font-medium text-zinc-300">Senha</label>
                        <a
                            :href="`/m/${slug}/esqueci-senha`"
                            class="text-sm font-medium transition hover:underline"
                            :style="{ color: product.primary_color || '#0ea5e9' }"
                        >
                            Esqueci minha senha
                        </a>
                    </div>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl border border-zinc-600 bg-zinc-800/80 py-3 pl-4 pr-12 text-white placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/30"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1.5 text-zinc-400 transition hover:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/30"
                            :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="showPassword" class="h-5 w-5" />
                            <EyeOff v-else class="h-5 w-5" />
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-zinc-600 bg-zinc-800/80 text-[var(--ma-primary)] focus:ring-[var(--ma-primary)]/50"
                    />
                    <label for="remember" class="text-sm text-zinc-400">Lembrar de mim</label>
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl px-4 py-3.5 font-semibold text-white shadow-lg transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-50"
                    :style="{ backgroundColor: product.primary_color || '#0ea5e9' }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Entrando…' : 'Entrar' }}
                </button>
                <button
                    v-if="canShowInstallButton"
                    type="button"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-4 py-3 text-sm font-medium text-white backdrop-blur-sm transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                    @click="triggerInstall"
                >
                    <Smartphone class="h-4 w-4 shrink-0" />
                    Instalar App
                </button>
            </form>
        </div>
        <PwaInstallPrompt :app-name="product?.name || product?.title || 'App'" :slug="slug" />
    </div>
</template>
