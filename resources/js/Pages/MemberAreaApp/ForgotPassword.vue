<script setup>
import { computed } from 'vue';
import { useForm, Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    slug: { type: String, required: true },
    product: { type: Object, required: true },
});

const page = usePage();
const status = computed(() => page.props.flash?.status ?? null);

const form = useForm({ email: '' });

function submit() {
    form.post(`/m/${props.slug}/esqueci-senha`, {
        preserveScroll: true,
        onFinish: () => form.reset('email'),
    });
}

const backgroundStyle = () => {
    if (props.product.background_image) {
        return { backgroundImage: `url(${props.product.background_image})` };
    }
    return { backgroundColor: props.product.background_color || '#18181b' };
};
</script>

<template>
    <Head>
        <title>Recuperar senha – {{ product.title || product.name || 'Área de Membros' }}</title>
        <meta name="theme-color" :content="product.primary_color || '#0ea5e9'" />
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
                    Informe seu e-mail para receber o link de redefinição de senha.
                </p>
            </div>

            <div v-if="status" class="mt-6 rounded-xl bg-emerald-500/20 px-4 py-3 text-sm text-emerald-300">
                {{ status }}
            </div>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
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
                <button
                    type="submit"
                    class="w-full rounded-xl px-4 py-3.5 font-semibold text-white shadow-lg transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-zinc-900 disabled:opacity-50"
                    :style="{ backgroundColor: product.primary_color || '#0ea5e9' }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Enviando…' : 'Enviar link' }}
                </button>
            </form>

            <p class="mt-6 text-center">
                <a
                    :href="`/m/${slug}/login`"
                    class="text-sm font-medium transition hover:underline"
                    :style="{ color: product.primary_color || '#0ea5e9' }"
                >
                    Voltar ao login
                </a>
            </p>
        </div>
    </div>
</template>
