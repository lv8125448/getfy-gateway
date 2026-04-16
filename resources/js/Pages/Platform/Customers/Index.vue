<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import LayoutPlatform from '@/Layouts/LayoutPlatform.vue';
import { Search } from 'lucide-vue-next';

defineOptions({ layout: LayoutPlatform });

const props = defineProps({
    users: { type: Object, required: true },
    q: { type: String, default: null },
});

const page = usePage();
const searchQ = ref(props.q ?? '');

watch(
    () => props.q,
    (v) => {
        searchQ.value = v ?? '';
    }
);

function applySearch() {
    const q = searchQ.value?.trim() || undefined;
    router.get('/plataforma/clientes', { q }, { preserveState: true, replace: true });
}

const userRows = computed(() => props.users?.data ?? []);
</script>

<template>
    <div class="space-y-6">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Utilizadores com pelo menos uma compra concluída.</p>

        <form class="flex flex-wrap items-center gap-2" @submit.prevent="applySearch">
            <div class="relative min-w-[200px] flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                <input
                    v-model="searchQ"
                    type="search"
                    placeholder="Nome ou e-mail"
                    class="w-full rounded-xl border border-zinc-300 bg-white py-2 pl-9 pr-3 text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                />
            </div>
            <button
                type="submit"
                class="rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
            >
                Pesquisar
            </button>
        </form>

        <div
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-600 dark:text-zinc-400">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-600 dark:text-zinc-400">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-zinc-600 dark:text-zinc-400">E-mail</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-zinc-600 dark:text-zinc-400">Compras</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <tr v-for="u in userRows" :key="u.id" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400">{{ u.id }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-white">{{ u.name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ u.email }}</td>
                        <td class="px-4 py-3 text-right text-sm text-zinc-800 dark:text-zinc-200">
                            {{ u.purchases_count ?? 0 }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!userRows.length" class="rounded-xl border border-dashed border-zinc-200 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
            Nenhum cliente encontrado.
        </div>

        <nav
            v-if="(users?.links?.length ?? 0) > 3"
            class="flex flex-wrap items-center justify-center gap-2"
            aria-label="Paginação"
        >
            <a
                v-for="link in users.links"
                :key="link.label + String(link.url)"
                :href="link.url || undefined"
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="!link.url"
                :class="[
                    'relative inline-flex min-h-[2.25rem] items-center rounded-lg px-3 py-2 text-sm font-medium transition',
                    link.active
                        ? 'z-10 bg-[var(--color-primary)] text-white'
                        : link.url
                          ? 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'
                          : 'cursor-not-allowed text-zinc-400 dark:text-zinc-500',
                ]"
                v-html="link.label"
                @click.prevent="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })"
            />
        </nav>
    </div>
</template>
