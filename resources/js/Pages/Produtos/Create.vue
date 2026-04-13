<script setup>
import { useForm } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: LayoutInfoprodutor });

const form = useForm({
    name: '',
    slug: '',
    description: '',
    type: 'area_membros',
    price: 0,
    is_active: true,
});
</script>

<template>
    <div class="space-y-4">
            <form class="max-w-xl space-y-4" @submit.prevent="form.post('/produtos')">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome</label>
                    <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Slug (opcional)</label>
                    <input v-model="form.slug" type="text" class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Descrição</label>
                    <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2"></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipo</label>
                        <select v-model="form.type" class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2">
                            <option value="area_membros">Área de membros</option>
                            <option value="area_membros_externa">Área de membros externa</option>
                            <option value="link">Link</option>
                            <option value="link_pagamento">Somente link de pagamento</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Preço (R$)</label>
                        <input v-model="form.price" type="number" step="0.01" min="0" required class="mt-1 block w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2" />
                        <p v-if="form.errors.price" class="mt-1 text-sm text-red-600">{{ form.errors.price }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input v-model="form.is_active" type="checkbox" id="is_active" class="h-4 w-4 rounded border-zinc-300" />
                    <label for="is_active" class="text-sm text-zinc-700 dark:text-zinc-300">Ativo</label>
                </div>
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Criar</Button>
                    <Link href="/produtos" class="inline-flex items-center rounded-lg border border-zinc-300 dark:border-zinc-600 px-4 py-2 text-sm font-medium">Cancelar</Link>
                </div>
            </form>
        </div>
</template>
