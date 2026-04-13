<script setup>
import { Link } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { MessageSquare } from 'lucide-vue-next';
import { getCommunityPageIconComponent } from '@/utils/communityPageIcons';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    pages: { type: Array, default: () => [] },
    slug: { type: String, required: true },
});

const basePath = `/m/${props.slug}/comunidade`;
</script>

<template>
    <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
        <!-- Sidebar: lista de páginas -->
        <aside class="w-full shrink-0 rounded-2xl border border-zinc-700 bg-zinc-800/50 shadow-lg lg:w-72">
            <div class="border-b border-zinc-700 p-4">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">
                    <MessageSquare class="h-4 w-4" />
                    Páginas
                </h2>
            </div>
            <nav class="p-2">
                <Link
                    v-for="p in pages"
                    :key="p.id"
                    :href="`${basePath}/${p.slug}`"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-zinc-300 transition hover:bg-zinc-700/50 hover:text-white"
                >
                    <template v-if="p.icon">
                        <component v-if="getCommunityPageIconComponent(p.icon)" :is="getCommunityPageIconComponent(p.icon)" class="h-5 w-5 shrink-0 text-[var(--ma-primary)]" />
                        <span v-else class="text-xl leading-none">{{ p.icon }}</span>
                    </template>
                    <img v-else-if="p.banner_url" :src="p.banner_url" :alt="p.title" class="h-8 w-10 shrink-0 rounded-lg object-cover" />
                    <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--ma-primary)]/20">
                        <MessageSquare class="h-4 w-4 text-[var(--ma-primary)]" />
                    </span>
                    <span class="truncate">{{ p.title }}</span>
                </Link>
            </nav>
        </aside>

        <!-- Conteúdo principal -->
        <main class="min-w-0 flex-1 space-y-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Comunidade</h1>
                <p class="mt-2 text-zinc-400">Escolha uma página ao lado ou acesse diretamente:</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="p in pages"
                    :key="p.id"
                    :href="`${basePath}/${p.slug}`"
                    class="group relative overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-800/50 shadow-lg transition hover:border-[var(--ma-primary)]/40 hover:shadow-xl"
                >
                    <div v-if="p.banner_url" class="aspect-[2/1] w-full bg-zinc-700">
                        <img :src="p.banner_url" :alt="p.title" class="h-full w-full object-cover transition group-hover:scale-[1.02]" />
                    </div>
                    <div class="flex items-center gap-4 p-4">
                        <template v-if="p.icon">
                            <span v-if="getCommunityPageIconComponent(p.icon)" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--ma-primary)]/20">
                                <component :is="getCommunityPageIconComponent(p.icon)" class="h-6 w-6 text-[var(--ma-primary)]" />
                            </span>
                            <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--ma-primary)]/20 text-2xl">{{ p.icon }}</span>
                        </template>
                        <img v-else-if="p.banner_url" :src="p.banner_url" :alt="p.title" class="h-12 w-12 shrink-0 rounded-xl object-cover" />
                        <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--ma-primary)]/20">
                            <MessageSquare class="h-6 w-6 text-[var(--ma-primary)]" />
                        </div>
                        <span class="font-semibold text-zinc-200 group-hover:text-white">{{ p.title }}</span>
                    </div>
                </Link>
            </div>
        </main>
    </div>
</template>
