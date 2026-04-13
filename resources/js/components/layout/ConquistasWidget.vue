<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { formatCompactCurrency } from '@/lib/utils';

const props = defineProps({
    variant: { type: String, default: 'header' }, // 'header' | 'sidebar' | 'dashboard'
});

const page = usePage();
const progress = computed(() => page.props.achievementsProgress ?? null);

const iconUrl = computed(() => {
    if (!progress.value) return null;
    const curr = progress.value.current_achievement;
    const achievements = progress.value.achievements ?? [];
    const first = achievements[0];
    if (curr?.image) return curr.image;
    if (first?.image) return first.image;
    return null;
});

const isLocked = computed(() => {
    if (!progress.value) return true;
    return progress.value.current_achievement === null;
});

const progressPercent = computed(() => {
    return progress.value?.progress_percent ?? 0;
});

const nextLabel = computed(() => {
    const next = progress.value?.next_achievement;
    if (!next) return null;
    return formatCompactCurrency(next.threshold);
});

const totalLabel = computed(() => {
    const total = progress.value?.total_valid_sales ?? 0;
    return formatCompactCurrency(total);
});

const panelNavPrefetch = ['hover', 'click'];
</script>

<template>
    <Link
        v-if="progress"
        href="/conquistas"
        :prefetch="panelNavPrefetch"
        class="group flex shrink-0 items-center gap-3 rounded-lg px-3 py-2 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-800"
        :class="{
            'flex-col items-stretch gap-2': props.variant === 'sidebar',
            'w-full rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700': props.variant === 'dashboard',
        }"
        title="Conquistas"
    >
        <div
            class="flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-800"
            :class="[
                props.variant === 'sidebar' ? 'h-12 w-12' : 'h-10 w-10',
                { 'opacity-60 grayscale': isLocked },
            ]"
        >
            <img
                v-if="iconUrl"
                :src="iconUrl"
                alt=""
                :class="[
                    props.variant === 'sidebar' ? 'h-8 w-8' : 'h-7 w-7',
                    'object-contain',
                ]"
            />
        </div>
        <div
            class="min-w-0 flex-1"
            :class="[
                props.variant === 'sidebar' ? 'w-full' : '',
                props.variant === 'dashboard' ? 'w-full' : '',
                props.variant === 'header' ? 'hidden w-[130px] sm:block' : '',
            ]"
        >
            <p
                v-if="props.variant === 'header' || props.variant === 'dashboard'"
                class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-400"
            >
                FATURAMENTO
            </p>
            <div
                class="w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"
                :class="[
                    props.variant === 'sidebar' || props.variant === 'dashboard' ? 'h-2.5' : 'h-2',
                ]"
            >
                <div
                    class="h-full rounded-full bg-[var(--color-primary)] transition-all duration-500"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
            <p
                class="mt-1 truncate text-zinc-500 dark:text-zinc-400"
                :class="[
                    props.variant === 'sidebar' || props.variant === 'dashboard' ? 'text-xs' : 'text-[11px]',
                ]"
            >
                {{ totalLabel }}
                <span v-if="nextLabel"> → {{ nextLabel }}</span>
            </p>
        </div>
    </Link>
</template>
