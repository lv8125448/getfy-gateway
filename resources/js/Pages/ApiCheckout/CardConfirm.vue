<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

defineOptions({ layout: null });

const props = defineProps({
    client_secret: { type: String, required: true },
    return_url: { type: String, default: '/' },
    stripe_publishable_key: { type: String, default: '' },
});

const status = ref('loading');
const message = ref('Confirmando pagamento...');

onMounted(async () => {
    if (!props.stripe_publishable_key?.trim() || !props.client_secret?.trim()) {
        status.value = 'error';
        message.value = 'Configuração inválida. Redirecionando.';
        setTimeout(() => { window.location.href = props.return_url || '/'; }, 2000);
        return;
    }
    try {
        const { loadStripe } = await import('@stripe/stripe-js');
        const stripe = await loadStripe(props.stripe_publishable_key.trim());
        if (!stripe) throw new Error('Stripe não carregou');
        const { error } = await stripe.confirmCardPayment(props.client_secret);
        if (error) {
            message.value = error.message || 'Falha na confirmação.';
            status.value = 'error';
            setTimeout(() => { window.location.href = props.return_url || '/'; }, 3000);
            return;
        }
        status.value = 'success';
        message.value = 'Pagamento confirmado! Redirecionando...';
        window.location.href = props.return_url || '/';
    } catch (e) {
        status.value = 'error';
        message.value = e?.message || 'Erro ao confirmar. Redirecionando.';
        setTimeout(() => { window.location.href = props.return_url || '/'; }, 3000);
    }
});
</script>

<template>
    <Head title="Confirmar pagamento" />
    <div class="min-h-screen bg-gray-100 flex flex-col items-center justify-center p-6">
        <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-8 shadow-lg text-center">
            <p class="text-gray-700 font-medium">{{ message }}</p>
            <div v-if="status === 'loading'" class="mt-4 flex justify-center">
                <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-gray-600" />
            </div>
        </div>
    </div>
</template>
