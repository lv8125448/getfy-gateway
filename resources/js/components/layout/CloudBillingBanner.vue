<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';

const page = usePage();
const cloudMode = computed(() => !!page.props.cloud_mode);
const renewWindowDays = computed(() => Number(page.props.cloud_billing_renew_window_days ?? 7));

const status = ref(null);
const loading = ref(false);

const CACHE_KEY = 'getfy:cloud:billing:status:v1';
const DAY_MS = 24 * 60 * 60 * 1000;
const BILLING_PORTAL_URL = 'http://getfy.cloud/login';

function parseDate(v) {
    if (!v) return null;
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? null : d;
}

function daysBetween(a, b) {
    if (!a || !b) return null;
    return Math.ceil((b.getTime() - a.getTime()) / DAY_MS);
}

function readCache() {
    try {
        const raw = localStorage.getItem(CACHE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        if (typeof parsed.ts !== 'number') return null;
        return parsed;
    } catch {
        return null;
    }
}

function writeCache(data) {
    try {
        localStorage.setItem(CACHE_KEY, JSON.stringify({ ts: Date.now(), data }));
    } catch {}
}

function isFresh(ts, minutes) {
    return Date.now() - ts <= minutes * 60 * 1000;
}

async function fetchStatus({ allowStaleFallback }) {
    if (!cloudMode.value) return;

    const cache = readCache();
    if (cache && isFresh(cache.ts, 10)) {
        status.value = cache.data;
        return;
    }

    loading.value = true;
    try {
        const res = await fetch('/cloud/billing/status', {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const contentType = res.headers.get('content-type') ?? '';
        if (!contentType.includes('application/json')) {
            throw new Error('not json');
        }

        const data = await res.json();
        if (!data || typeof data !== 'object') {
            throw new Error('invalid json');
        }

        status.value = data;
        writeCache(data);
    } catch {
        if (allowStaleFallback && cache && isFresh(cache.ts, 60)) {
            status.value = cache.data;
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    fetchStatus({ allowStaleFallback: true });
});

const banner = computed(() => {
    if (!cloudMode.value) return null;
    if (!status.value || typeof status.value !== 'object') return null;
    if (!status.value.enabled) return null;

    const serverNow = parseDate(status.value.serverNow) ?? new Date();
    const paidThrough = parseDate(status.value.paidThrough);
    const overdueSince = parseDate(status.value.overdueSince);

    const paymentRequired = !!status.value.paymentRequired;
    const inGracePeriod = !!status.value.inGracePeriod;
    const graceDays = Number(status.value.graceDays ?? 3);

    const portalUrl = BILLING_PORTAL_URL;

    if (!paymentRequired) {
        const daysLeft = paidThrough ? daysBetween(serverNow, paidThrough) : null;
        if (daysLeft === null || daysLeft > renewWindowDays.value || daysLeft <= 0) {
            return null;
        }

        return {
            tone: 'info',
            title: 'Sua assinatura já pode ser renovada',
            subtitle: daysLeft === 1 ? 'Vence em 1 dia.' : `Vence em ${daysLeft} dias.`,
            ctaLabel: 'Renovar agora',
            ctaUrl: portalUrl,
        };
    }

    if (inGracePeriod) {
        let subtitle = 'Sua assinatura venceu.';
        if (overdueSince) {
            const endGrace = new Date(overdueSince.getTime() + graceDays * DAY_MS);
            const daysToSuspension = Math.max(0, Math.ceil((endGrace.getTime() - serverNow.getTime()) / DAY_MS));
            subtitle = daysToSuspension <= 0
                ? 'Sua assinatura venceu. Fim do período de carência.'
                : (daysToSuspension === 1
                    ? 'Sua assinatura venceu. Carência por mais 1 dia.'
                    : `Sua assinatura venceu. Carência por mais ${daysToSuspension} dias.`);
        }

        return {
            tone: 'warning',
            title: 'Assinatura vencida',
            subtitle,
            ctaLabel: 'Renovar agora',
            ctaUrl: portalUrl,
        };
    }

    return {
        tone: 'danger',
        title: 'Assinatura vencida',
        subtitle: 'Já passou o período de carência. Pode ser suspenso a qualquer momento.',
        ctaLabel: 'Renovar agora',
        ctaUrl: portalUrl,
    };
});

const toneClasses = computed(() => {
    if (!banner.value) return '';
    if (banner.value.tone === 'danger') {
        return 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-100';
    }
    if (banner.value.tone === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-100';
    }
    return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-100';
});

const buttonVariant = computed(() => {
    if (!banner.value) return 'default';
    if (banner.value.tone === 'danger') return 'destructive';
    if (banner.value.tone === 'warning') return 'secondary';
    return 'primary';
});
</script>

<template>
    <div v-if="banner" class="w-full px-3 md:px-4 lg:px-6">
        <div class="flex w-full items-start justify-between gap-3 rounded-xl border px-4 py-3" :class="toneClasses">
            <div class="min-w-0">
                <div class="text-sm font-semibold leading-5">
                    {{ banner.title }}
                </div>
                <div class="mt-0.5 text-sm opacity-90">
                    {{ banner.subtitle }}
                </div>
            </div>

            <div class="shrink-0">
                <Button
                    v-if="banner.ctaUrl && banner.ctaLabel"
                    as="a"
                    :href="banner.ctaUrl"
                    target="_blank"
                    rel="noopener"
                    size="sm"
                    :variant="buttonVariant"
                >
                    {{ banner.ctaLabel }}
                    <ExternalLink class="h-4 w-4" />
                </Button>
                <div v-else-if="loading" class="text-xs opacity-70">
                    Carregando…
                </div>
            </div>
        </div>
    </div>
</template>
