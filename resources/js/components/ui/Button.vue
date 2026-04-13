<script setup>
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'primary', 'destructive', 'outline', 'secondary', 'ghost', 'link'].includes(v),
    },
    size: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'sm', 'lg', 'icon'].includes(v),
    },
    as: { type: String, default: 'button' },
    class: { type: [String, Object, Array], default: '' },
});

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 dark:focus-visible:ring-zinc-300 dark:focus-visible:ring-offset-zinc-950',
    {
        variants: {
            variant: {
                default: 'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200',
                primary: 'bg-[var(--color-primary)] text-white hover:opacity-90 dark:hover:opacity-90',
                destructive: 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-900 dark:hover:bg-red-800',
                outline: 'border border-zinc-200 bg-white hover:bg-zinc-100 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-800',
                secondary: 'bg-zinc-100 text-zinc-900 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700',
                ghost: 'hover:bg-zinc-100 dark:hover:bg-zinc-800',
                link: 'text-zinc-900 underline-offset-4 hover:underline dark:text-zinc-100',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 rounded-md px-3 text-xs',
                lg: 'h-11 rounded-lg px-8 text-base',
                icon: 'h-10 w-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    }
);
</script>

<template>
    <component
        :is="as"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <slot />
    </component>
</template>
