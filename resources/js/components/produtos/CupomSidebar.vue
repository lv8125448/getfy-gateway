<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { X, ChevronDown, Check } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    produtos: { type: Array, default: () => [] },
    coupon: { type: Object, default: null },
});

const emit = defineEmits(['close', 'success']);

const isEdit = computed(() => !!props.coupon);
const productsDropdownOpen = ref(false);

const form = useForm({
    code: '',
    type: 'percent',
    value: '',
    product_ids: [],
    min_amount: '',
    max_uses: '',
    valid_from: '',
    valid_until: '',
    is_active: true,
});

function toggleProduct(id) {
    const idx = form.product_ids.indexOf(id);
    if (idx === -1) {
        form.product_ids = [...form.product_ids, id];
    } else {
        form.product_ids = form.product_ids.filter((pid) => pid !== id);
    }
}

function isProductSelected(id) {
    return form.product_ids.includes(id);
}

const productsLabel = computed(() => {
    if (!form.product_ids.length) return 'Todos os produtos';
    if (form.product_ids.length === props.produtos.length) return 'Todos os produtos';
    if (form.product_ids.length === 1) {
        const p = props.produtos.find((x) => x.id === form.product_ids[0]);
        return p ? p.name : '1 produto';
    }
    return `${form.product_ids.length} produtos selecionados`;
});

function closeProductsDropdown() {
    productsDropdownOpen.value = false;
}

function handleClickOutsideProducts(event) {
    const el = document.querySelector('[data-cupom-products-dropdown]');
    if (el && !el.contains(event.target)) closeProductsDropdown();
}

onMounted(() => {
    document.addEventListener('click', handleClickOutsideProducts);
});
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutsideProducts);
});

function close() {
    form.reset();
    emit('close');
}

function submit() {
    const payload = {
        code: form.code,
        type: form.type,
        value: parseFloat(form.value) || 0,
        product_ids: form.product_ids,
        min_amount: form.min_amount ? parseFloat(form.min_amount) : null,
        max_uses: form.max_uses ? parseInt(form.max_uses, 10) : null,
        valid_from: form.valid_from || null,
        valid_until: form.valid_until || null,
        is_active: form.is_active,
    };
    if (isEdit.value) {
        form.transform(() => payload).put(`/produtos/cupons/${props.coupon.id}`, {
            onSuccess: () => {
                close();
                emit('success');
            },
        });
    } else {
        form.transform(() => payload).post('/produtos/cupons', {
            onSuccess: () => {
                close();
                emit('success');
            },
        });
    }
}

watch(
    () => [props.open, props.coupon],
    () => {
        if (props.open && props.coupon) {
            form.code = props.coupon.code;
            form.type = props.coupon.type;
            form.value = String(props.coupon.value ?? '');
            form.product_ids = Array.isArray(props.coupon.product_ids) ? [...props.coupon.product_ids] : (props.coupon.product_id ? [props.coupon.product_id] : []);
            form.min_amount = props.coupon.min_amount != null ? String(props.coupon.min_amount) : '';
            form.max_uses = props.coupon.max_uses != null ? String(props.coupon.max_uses) : '';
            form.valid_from = props.coupon.valid_from ? props.coupon.valid_from.slice(0, 16) : '';
            form.valid_until = props.coupon.valid_until ? props.coupon.valid_until.slice(0, 16) : '';
            form.is_active = !!props.coupon.is_active;
        } else if (props.open && !props.coupon) {
            form.reset();
            form.type = 'percent';
            form.is_active = true;
            form.product_ids = [];
        }
    },
    { immediate: true }
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100000] flex justify-end"
            aria-modal="true"
            role="dialog"
            :aria-labelledby="isEdit ? 'sidebar-edit-cupom' : 'sidebar-new-cupom'"
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
                    <h2 :id="isEdit ? 'sidebar-edit-cupom' : 'sidebar-new-cupom'" class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ isEdit ? 'Editar cupom' : 'Novo cupom' }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Código *
                            </label>
                            <input
                                v-model="form.code"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                placeholder="Ex: PROMO20"
                            />
                            <p v-if="form.errors.code" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.code }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Tipo *
                            </label>
                            <select
                                v-model="form.type"
                                required
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="percent">Percentual (%)</option>
                                <option value="fixed">Valor fixo (R$)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Valor *
                            </label>
                            <input
                                v-model="form.value"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                :placeholder="form.type === 'percent' ? '0–100' : '0,00'"
                            />
                            <p v-if="form.errors.value" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.value }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Produtos
                            </label>
                            <div class="relative mt-1" data-cupom-products-dropdown>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-lg border border-zinc-300 bg-white px-3 py-2 text-left text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                                    aria-haspopup="listbox"
                                    :aria-expanded="productsDropdownOpen"
                                    @click="productsDropdownOpen = !productsDropdownOpen"
                                >
                                    <span class="truncate">{{ productsLabel }}</span>
                                    <ChevronDown
                                        class="h-4 w-4 shrink-0 transition-transform"
                                        :class="{ 'rotate-180': productsDropdownOpen }"
                                    />
                                </button>
                                <div
                                    v-show="productsDropdownOpen"
                                    class="absolute left-0 right-0 top-full z-50 mt-1 max-h-56 overflow-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                    role="listbox"
                                >
                                    <button
                                        v-for="p in produtos"
                                        :key="p.id"
                                        type="button"
                                        role="option"
                                        :aria-selected="isProductSelected(p.id)"
                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                        @click="toggleProduct(p.id)"
                                    >
                                        <span
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border border-zinc-300 dark:border-zinc-600"
                                            :class="isProductSelected(p.id) ? 'bg-[var(--color-primary)] border-[var(--color-primary)] text-white' : 'bg-white dark:bg-zinc-800'"
                                        >
                                            <Check v-if="isProductSelected(p.id)" class="h-3 w-3" stroke-width="3" />
                                        </span>
                                        {{ p.name }}
                                    </button>
                                    <p v-if="!produtos.length" class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        Nenhum produto cadastrado.
                                    </p>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Nenhum selecionado = cupom vale para todos os produtos.
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Pedido mínimo (R$)
                            </label>
                            <input
                                v-model="form.min_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                placeholder="Opcional"
                            />
                            <p v-if="form.errors.min_amount" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.min_amount }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Máximo de usos
                            </label>
                            <input
                                v-model="form.max_uses"
                                type="number"
                                min="1"
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 placeholder-zinc-400 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                                placeholder="Ilimitado"
                            />
                            <p v-if="form.errors.max_uses" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.max_uses }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Válido de
                            </label>
                            <input
                                v-model="form.valid_from"
                                type="datetime-local"
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Válido até
                            </label>
                            <input
                                v-model="form.valid_until"
                                type="datetime-local"
                                class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                            />
                            <p v-if="form.errors.valid_until" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.valid_until }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Toggle v-model="form.is_active" label="Cupom ativo" />
                        </div>
                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ isEdit ? 'Salvar' : 'Criar cupom' }}
                            </Button>
                            <Button type="button" variant="outline" @click="close">
                                Cancelar
                            </Button>
                        </div>
                    </form>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
