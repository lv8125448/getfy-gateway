<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';

const page = usePage();
const branding = computed(() => page.props.public_branding ?? {});
const primary = computed(() => branding.value.theme_primary || '#c8fa64');
const appName = computed(() => branding.value.app_name || 'Getfy');
const logoLight = computed(() => branding.value.app_logo_icon || 'https://cdn.getfy.cloud/collapsed-logo.png');
const logoDark = computed(() => branding.value.app_logo_icon_dark || logoLight.value);
const heroImage = computed(() => branding.value.login_hero_image || 'https://cdn.getfy.cloud/login.webp');

const props = defineProps({
    invalid: { type: Boolean, default: false },
    message: { type: String, default: '' },
    token: { type: String, default: '' },
    invitation: { type: Object, default: null },
    can_accept: { type: Boolean, default: false },
    auth_email: { type: String, default: null },
    login_url: { type: String, default: '/login' },
    register_url: { type: String, default: '/cadastro' },
});

const durationLabel = computed(() => {
    const p = props.invitation?.duration_preset;
    if (p === 'eternal') return 'Por tempo indeterminado';
    if (p === '30') return '30 dias';
    if (p === '60') return '60 dias';
    if (p === '90') return '90 dias';
    if (p === '120') return '120 dias';
    return p || '—';
});

function acceptInvite() {
    if (!props.token) return;
    router.post(`/coproducao/convite/${props.token}/aceitar`);
}
</script>

<template>
    <div class="wl-root flex min-h-screen">
        <div class="flex w-full flex-col justify-center px-8 py-12 lg:w-[36%] lg:min-w-[380px]">
            <div class="text-center">
                <img :src="logoLight" :alt="appName" class="mx-auto mb-8 h-12 w-auto object-contain dark:hidden" />
                <img :src="logoDark" :alt="appName" class="mx-auto mb-8 hidden h-12 w-auto object-contain dark:block" />
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Co-produção</h1>
            </div>

            <div
                v-if="invalid"
                class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 text-center text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
            >
                {{ message || 'Convite inválido.' }}
            </div>

            <div
                v-else-if="invitation"
                class="mt-8 space-y-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800"
            >
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ invitation.inviter_name }}</span>
                    convidou você para co-produzir
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ invitation.product_name }}</span>.
                </p>
                <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <li>E-mail do convite: <strong>{{ invitation.email }}</strong></li>
                    <li>Comissão: <strong>{{ invitation.commission_percent }}%</strong> sobre o bruto (taxas aplicadas na sua carteira)</li>
                    <li>Vendas do produtor: {{ invitation.commission_on_direct_sales ? 'Sim' : 'Não' }}</li>
                    <li>Vendas de afiliados: {{ invitation.commission_on_affiliate_sales ? 'Sim' : 'Não' }}</li>
                    <li>Duração após aceitar: <strong>{{ durationLabel }}</strong></li>
                </ul>

                <p v-if="invitation.status === 'active'" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">
                    Este convite já foi aceito.
                </p>

                <div v-else-if="invitation.status === 'pending'" class="space-y-3 pt-2">
                    <Button
                        v-if="can_accept"
                        type="button"
                        class="w-full"
                        :style="{ backgroundColor: primary }"
                        @click="acceptInvite"
                    >
                        Aceitar co-produção
                    </Button>
                    <p v-else-if="!auth_email" class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                        <Link :href="login_url" class="font-medium underline" :style="{ color: primary }">Entrar</Link>
                        com a conta
                        <strong>{{ invitation.email }}</strong>
                        ou
                        <Link :href="register_url" class="font-medium underline" :style="{ color: primary }">criar cadastro</Link>.
                    </p>
                    <p v-else class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Entre com <strong>{{ invitation.email }}</strong> para aceitar. Você está logado como {{ auth_email }}.
                        <Link :href="login_url" class="ml-1 font-medium underline" :style="{ color: primary }">Trocar de conta</Link>
                    </p>
                </div>
            </div>
        </div>
        <div class="relative hidden flex-1 lg:block">
            <img :src="heroImage" alt="" class="absolute inset-0 h-full w-full object-cover" />
        </div>
    </div>
</template>
