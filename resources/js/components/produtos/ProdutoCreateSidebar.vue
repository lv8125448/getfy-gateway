<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Smartphone,
    Users,
    BookOpen,
    Link,
    CreditCard,
    ChevronRight,
    X,
} from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    open: { type: Boolean, default: false },
    productTypes: { type: Array, default: () => [] },
    billingTypes: { type: Array, default: () => [] },
    exchangeRates: { type: Object, default: () => ({ brl_eur: 0.16, brl_usd: 0.18 }) },
    pluginFormSections: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'success']);
const { t } = useI18n();

const step = ref(1);
const selectedType = ref(null);

const typeIcons = {
    aplicativo: Smartphone,
    area_membros: Users,
    link: Link,
    link_pagamento: CreditCard,
};

const form = useForm({
    name: '',
    description: '',
    type: '',
    billing_type: 'one_time',
    price: '',
    currency: 'BRL',
    is_active: true,
    image: null,
    deliverable_link: '',
});

const priceNum = computed(() => parseFloat(form.price) || 0);
const priceEur = computed(() => (priceNum.value * (props.exchangeRates.brl_eur ?? 0.16)).toFixed(2));
const priceUsd = computed(() => (priceNum.value * (props.exchangeRates.brl_usd ?? 0.18)).toFixed(2));

const availableTypes = computed(() =>
    props.productTypes.filter((t) => t.available)
);

function selectType(type) {
    if (!type.available) return;
    selectedType.value = type.value;
    form.type = type.value;
    step.value = 2;
}

function back() {
    step.value = 1;
    selectedType.value = null;
    form.type = '';
}

function close() {
    step.value = 1;
    selectedType.value = null;
    form.reset();
    emit('close');
}

function submit() {
    form.post('/produtos', {
        forceFormData: true,
        onSuccess: () => {
            close();
            emit('success');
        },
    });
}

function onFileChange(e) {
    const file = e.target.files?.[0];
    form.image = file || null;
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            step.value = 1;
            selectedType.value = null;
            form.reset();
        }
    }
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100000] flex justify-end"
            aria-modal="true"
            role="dialog"
            aria-labelledby="sidebar-title"
        >
            <div
                class="fixed inset-0 bg-zinc-900/50 dark:bg-zinc-950/70"
                aria-hidden="true"
                @click="close"
            />
            <aside
                class="relative z-[100001] flex h-full w-full max-w-md flex-col rounded-l-2xl bg-white shadow-xl dark:bg-zinc-900 sm:w-[420px]"
                @click.stop
            >
                <div
                    class="flex shrink-0 items-center justify-between rounded-tl-2xl border-b border-zinc-200 px-4 py-3 dark:border-zinc-800"
                >
                    <h2 id="sidebar-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ step === 1 ? t('products.create.new_product', 'Novo produto') : t('products.create.create_product', 'Criar produto') }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        :aria-label="t('common.close', 'Fechar')"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <!-- Step 1: Tipo -->
                    <div v-if="step === 1" class="space-y-3">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ t('products.create.choose_delivery_type', 'Escolha o tipo de entrega do produto.') }}
                        </p>
                        <div class="grid gap-3">
                            <button
                                v-for="typeOption in productTypes"
                                :key="typeOption.value"
                                type="button"
                                :disabled="!typeOption.available"
                                :class="[
                                    'flex items-start gap-3 rounded-xl border p-4 text-left transition',
                                    typeOption.available
                                        ? 'border-zinc-200 bg-zinc-50 hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 dark:border-zinc-700 dark:bg-zinc-800/50 dark:hover:border-[var(--color-primary)]'
                                        : 'cursor-not-allowed border-zinc-200 bg-zinc-100/50 opacity-70 dark:border-zinc-800 dark:bg-zinc-800/30',
                                ]"
                                @click="selectType(typeOption)"
                            >
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white dark:bg-zinc-700"
                                >
                                    <component
                                        :is="typeIcons[typeOption.value] || BookOpen"
                                        class="h-5 w-5 text-zinc-600 dark:text-zinc-300"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-zinc-900 dark:text-white">
                                            {{ typeOption.label }}
                                        </span>
                                        <span
                                            v-if="!typeOption.available"
                                            class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200"
                                        >
                                            {{ t('common.coming_soon', 'Em breve') }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ typeOption.description }}
                                    </p>
                                </div>
                                <ChevronRight
                                    v-if="typeOption.available"
                                    class="h-5 w-5 shrink-0 text-zinc-400"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Formulário -->
                    <form v-else class="space-y-4" @submit.prevent="submit">
                        <div>
                            <button
                                type="button"
                                class="mb-2 text-sm text-[var(--color-primary)] hover:underline"
                                @click="back"
                            >
                                {{ t('products.create.back_to_type', '← Voltar ao tipo') }}
                            </button>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ t('common.name', 'Nome') }} *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                :placeholder="t('products.create.name_placeholder', 'Ex: Curso de Desenvolvimento Web')"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ t('products.create.billing_type', 'Tipo de cobrança') }}
                            </label>
                            <div class="mt-1.5 flex gap-2">
                                <button
                                    v-for="bt in billingTypes"
                                    :key="bt.value"
                                    type="button"
                                    :class="[
                                        'flex-1 rounded-lg border px-3 py-2.5 text-sm font-medium transition',
                                        form.billing_type === bt.value
                                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] dark:bg-[var(--color-primary)]/20'
                                            : 'border-zinc-300 bg-white text-zinc-600 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700',
                                    ]"
                                    @click="form.billing_type = bt.value"
                                >
                                    {{ bt.label }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ t('common.description', 'Descrição') }}
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                :placeholder="t('products.create.description_placeholder', 'Breve descrição do produto')"
                            />
                        </div>
                        <div v-if="form.type === 'link'">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ t('products.create.deliverable_link', 'Link do entregável') }}
                            </label>
                            <input
                                v-model="form.deliverable_link"
                                type="url"
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                placeholder="https://..."
                            />
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ t('products.create.deliverable_link_hint', 'Enviado por e-mail após a compra.') }}
                            </p>
                            <p v-if="form.errors.deliverable_link" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.deliverable_link }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ t('products.create.price_brl', 'Preço (BRL)') }} *
                            </label>
                            <input
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                placeholder="0,00"
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                ≈ € {{ priceEur }} · $ {{ priceUsd }}
                            </p>
                            <p v-if="form.errors.price" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.price }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ t('common.image', 'Imagem') }}
                            </label>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ t('products.create.image_hint', 'Exibida em formato quadrado (1:1). Recomendado enviar imagem quadrada.') }}
                            </p>
                            <input
                                type="file"
                                accept="image/*"
                                class="mt-1 block w-full text-sm text-zinc-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary)] file:px-4 file:py-2 file:text-white dark:text-zinc-400"
                                @change="onFileChange"
                            />
                            <p v-if="form.image" class="mt-1 text-sm text-zinc-500">
                                {{ form.image.name }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Toggle v-model="form.is_active" :label="t('products.create.active_product', 'Produto ativo')" />
                        </div>
                        <!-- Área para plugins -->
                        <div v-if="pluginFormSections?.length" class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <template v-for="(section, idx) in pluginFormSections" :key="idx">
                                <div v-if="section.html" v-html="section.html" />
                                <div v-else-if="section.slot" class="text-sm text-zinc-500">
                                    {{ section.slot }}
                                </div>
                            </template>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ t('products.create.create_product', 'Criar produto') }}
                            </Button>
                            <Button type="button" variant="outline" @click="close">
                                {{ t('common.cancel', 'Cancelar') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
