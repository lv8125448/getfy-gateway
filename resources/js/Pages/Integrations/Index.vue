<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import AppCard from '@/components/integrations/AppCard.vue';
import SpedySidebar from '@/components/integrations/SpedySidebar.vue';
import UtmifySidebar from '@/components/integrations/UtmifySidebar.vue';
import WebhookSidebar from '@/components/integrations/WebhookSidebar.vue';
import CademiSidebar from '@/components/integrations/CademiSidebar.vue';
import { Zap } from 'lucide-vue-next';
import { useI18n } from '@/composables/useI18n';

defineOptions({ layout: LayoutInfoprodutor });
const { t } = useI18n();

const APPS_BASE = [
    {
        id: 'webhook',
        name: 'Webhook',
        description: t('integrations.webhook.description', 'Envie eventos da plataforma para sua URL. Configure quais eventos deseja receber e use Bearer token para autenticação.'),
        image: 'images/integrations/webhook.png',
    },
    {
        id: 'utmify',
        name: 'UTMfy',
        description: t('integrations.utmify.description', 'Rastreie vendas e envie eventos para a UTMfy. Requer apenas a chave de API.'),
        image: 'images/integrations/utmify.jpg',
    },
    {
        id: 'spedy',
        name: 'Spedy',
        description: t('integrations.spedy.description', 'Emissão automática de notas fiscais. Envie vendas para a Spedy e emita NF-e/NFS-e.'),
        image: 'images/integrations/spedy.png',
    },
    {
        id: 'cademi',
        name: 'Cademí',
        description: t('integrations.cademi.description', 'Área de membros externa. Após a compra, sincronize o aluno e conceda acesso na Cademí.'),
        image: 'images/integrations/cademi.png',
    },
];

const props = defineProps({
    webhooks: { type: Array, default: () => [] },
    webhook_events: { type: Object, default: () => ({}) },
    utmify_integrations: { type: Array, default: () => [] },
    spedy_integrations: { type: Array, default: () => [] },
    cademi_integrations: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const APPS = computed(() =>
    APPS_BASE.map((app) => {
        if (app.id === 'utmify') {
            const hasActive = (props.utmify_integrations || []).some(
                (i) => i.configured && i.is_active
            );
            return {
                ...app,
                status: hasActive ? 'active' : undefined,
            };
        }
        if (app.id === 'spedy') {
            const hasActive = (props.spedy_integrations || []).some(
                (i) => i.configured && i.is_active
            );
            return {
                ...app,
                status: hasActive ? 'active' : undefined,
            };
        }
        if (app.id === 'cademi') {
            const hasActive = (props.cademi_integrations || []).some(
                (i) => i.configured && i.is_active
            );
            return {
                ...app,
                status: hasActive ? 'active' : undefined,
            };
        }
        return app;
    })
);

const webhookSidebarOpen = ref(false);
const utmifySidebarOpen = ref(false);
const spedySidebarOpen = ref(false);
const cademiSidebarOpen = ref(false);

function openWebhookSidebar() {
    webhookSidebarOpen.value = true;
}

function closeWebhookSidebar() {
    webhookSidebarOpen.value = false;
}

function openUtmifySidebar() {
    utmifySidebarOpen.value = true;
}

function closeUtmifySidebar() {
    utmifySidebarOpen.value = false;
}

function openSpedySidebar() {
    spedySidebarOpen.value = true;
}

function closeSpedySidebar() {
    spedySidebarOpen.value = false;
}

function openCademiSidebar() {
    cademiSidebarOpen.value = true;
}

function closeCademiSidebar() {
    cademiSidebarOpen.value = false;
}

function onWebhookSaved() {
    router.reload();
}

function onUtmifySaved() {
    router.reload({ only: ['utmify_integrations', 'products'] });
}

function onSpedySaved() {
    router.reload({ only: ['spedy_integrations', 'products'] });
}

function onCademiSaved() {
    router.reload({ only: ['cademi_integrations', 'products'] });
}

function onAppClick(app) {
    if (app.id === 'webhook') {
        openWebhookSidebar();
    } else if (app.id === 'utmify') {
        openUtmifySidebar();
    } else if (app.id === 'spedy') {
        openSpedySidebar();
    } else if (app.id === 'cademi') {
        openCademiSidebar();
    }
}
</script>

<template>
    <div class="space-y-6">
        <section>
            <div class="mb-4 flex items-center gap-2">
                <Zap class="h-5 w-5 text-[var(--color-primary)]" aria-hidden="true" />
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ t('integrations.title', 'Apps') }}
                </h2>
            </div>
            <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">
                {{ t('integrations.subtitle', 'Conecte sua plataforma com sistemas externos via webhooks e outras integrações. Os gateways de pagamento são configurados no painel da plataforma (operador).') }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <AppCard
                    v-for="app in APPS"
                    :key="app.id"
                    :app="app"
                    @click="onAppClick(app)"
                />
            </div>
        </section>

        <WebhookSidebar
            :open="webhookSidebarOpen"
            :webhooks="webhooks"
            :webhook-events="webhook_events"
            :products="products"
            @close="closeWebhookSidebar"
            @saved="onWebhookSaved"
        />
        <UtmifySidebar
            :open="utmifySidebarOpen"
            :utmify_integrations="utmify_integrations"
            :products="products"
            @close="closeUtmifySidebar"
            @saved="onUtmifySaved"
        />
        <SpedySidebar
            :open="spedySidebarOpen"
            :spedy_integrations="spedy_integrations"
            :products="products"
            @close="closeSpedySidebar"
            @saved="onSpedySaved"
        />
        <CademiSidebar
            :open="cademiSidebarOpen"
            :cademi_integrations="cademi_integrations"
            :products="products"
            @close="closeCademiSidebar"
            @saved="onCademiSaved"
        />
    </div>
</template>
