<script setup>
import { ref, computed, watch, watchEffect } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { User, Building2, ChevronLeft } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';

const page = usePage();
const branding = computed(() => page.props.public_branding ?? {});
const primary = computed(() => branding.value.theme_primary || '#c8fa64');
const appName = computed(() => branding.value.app_name || 'Getfy');
const logoLight = computed(() => branding.value.app_logo_icon || 'https://cdn.getfy.cloud/collapsed-logo.png');
const logoDark = computed(() => branding.value.app_logo_icon_dark || logoLight.value);
const heroImage = computed(() => branding.value.login_hero_image || 'https://cdn.getfy.cloud/login.webp');

const props = defineProps({
    revenue_ranges: { type: Array, default: () => [] },
    coproducer_invite: { type: String, default: '' },
});

const step = ref(1);
const totalSteps = 5;
const emailCheckMsg = ref('');
const cepLoading = ref(false);
/** Aviso informativo após consulta ViaCEP (não bloqueia; endereço pode ser manual). */
const cepLookupHint = ref('');
let cepFetchTimer = null;
let cepAbortController = null;
/** Erro de validação apenas do passo atual (avanço Continuar / Enter). */
const wizardStepError = ref('');

function digitsOnly(s) {
    return String(s || '').replace(/\D/g, '');
}

/** Mesma lógica que `App\Support\BrazilianDocuments` (dígitos verificadores). */
function isValidCpfJs(d) {
    const cpf = digitsOnly(d);
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }
    for (let t = 9; t < 11; t++) {
        let sum = 0;
        for (let i = 0; i < t; i++) {
            sum += parseInt(cpf[i], 10) * (t + 1 - i);
        }
        let r = (sum * 10) % 11;
        if (r === 10) {
            r = 0;
        }
        if (r !== parseInt(cpf[t], 10)) {
            return false;
        }
    }
    return true;
}

function isValidCnpjJs(d) {
    const cnpj = digitsOnly(d);
    if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) {
        return false;
    }
    const w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(cnpj[i], 10) * w1[i];
    }
    let r = sum % 11;
    const dv1 = r < 2 ? 0 : 11 - r;
    if (dv1 !== parseInt(cnpj[12], 10)) {
        return false;
    }
    const w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    sum = 0;
    for (let i = 0; i < 13; i++) {
        sum += parseInt(cnpj[i], 10) * w2[i];
    }
    r = sum % 11;
    const dv2 = r < 2 ? 0 : 11 - r;
    return dv2 === parseInt(cnpj[13], 10);
}

function isAdultBirthDate(dateStr) {
    if (!dateStr) {
        return false;
    }
    const d = new Date(`${dateStr}T12:00:00`);
    if (Number.isNaN(d.getTime())) {
        return false;
    }
    const limit = new Date();
    limit.setFullYear(limit.getFullYear() - 18);
    return d <= limit;
}

function isValidEmailFormat(email) {
    const e = String(email || '').trim();
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
}

/**
 * Valida só o passo atual. Retorna true se pode avançar ou enviar.
 * Passo 1 consulta o e-mail no servidor (evita avançar sem blur no campo).
 */
async function validateCurrentStep() {
    wizardStepError.value = '';
    const s = step.value;

    if (s === 1) {
        if (!String(form.name || '').trim()) {
            wizardStepError.value = 'Informe seu nome completo.';
            return false;
        }
        if (!isValidEmailFormat(form.email)) {
            wizardStepError.value = 'Informe um e-mail válido.';
            return false;
        }
        if (!form.birth_date) {
            wizardStepError.value = 'Informe a data de nascimento.';
            return false;
        }
        if (!isAdultBirthDate(form.birth_date)) {
            wizardStepError.value = 'É necessário ter pelo menos 18 anos.';
            return false;
        }
        const email = String(form.email || '').trim();
        try {
            const res = await window.axios.post('/cadastro/validar-email', { email });
            if (!res.data?.available) {
                emailCheckMsg.value = 'Este e-mail já está em uso.';
                wizardStepError.value = 'Este e-mail já está em uso. Escolha outro.';
                return false;
            }
            emailCheckMsg.value = '';
        } catch {
            /* não bloqueia se a checagem falhar (rede); o backend valida no POST final */
        }
        return true;
    }

    if (s === 2) {
        const doc = digitsOnly(form.document);
        if (form.person_type === 'pf') {
            if (!isValidCpfJs(doc)) {
                wizardStepError.value = 'CPF inválido. Confira os números.';
                return false;
            }
        } else {
            if (!isValidCnpjJs(doc)) {
                wizardStepError.value = 'CNPJ inválido. Confira os números.';
                return false;
            }
            if (!String(form.company_name || '').trim()) {
                wizardStepError.value = 'Informe a razão social.';
                return false;
            }
            const rep = digitsOnly(form.legal_representative_cpf);
            if (!isValidCpfJs(rep)) {
                wizardStepError.value = 'CPF do representante legal inválido.';
                return false;
            }
        }
        try {
            const payload = {
                person_type: form.person_type,
                document: doc,
                legal_representative_cpf: form.person_type === 'pj' ? digitsOnly(form.legal_representative_cpf) : null,
            };
            const res = await window.axios.post('/cadastro/validar-documento', payload);
            if (!res.data?.available) {
                wizardStepError.value = res.data?.message || 'Documento não disponível para cadastro.';
                return false;
            }
        } catch (e) {
            const msg = e.response?.data?.message;
            if (msg) {
                wizardStepError.value = msg;
                return false;
            }
            /* rede: não bloqueia; o servidor valida de novo no POST */
        }
        return true;
    }

    if (s === 3) {
        const cep = digitsOnly(form.address_zip);
        if (cep.length !== 8) {
            wizardStepError.value = 'Informe o CEP com 8 dígitos.';
            return false;
        }
        if (!String(form.address_street || '').trim()) {
            wizardStepError.value = 'Informe o logradouro.';
            return false;
        }
        if (!String(form.address_number || '').trim()) {
            wizardStepError.value = 'Informe o número.';
            return false;
        }
        if (!String(form.address_neighborhood || '').trim()) {
            wizardStepError.value = 'Informe o bairro.';
            return false;
        }
        if (!String(form.address_city || '').trim()) {
            wizardStepError.value = 'Informe a cidade.';
            return false;
        }
        const uf = String(form.address_state || '').trim().toUpperCase();
        if (uf.length !== 2) {
            wizardStepError.value = 'Informe a UF com 2 letras.';
            return false;
        }
        return true;
    }

    if (s === 4) {
        if (!form.monthly_revenue_range) {
            wizardStepError.value = 'Selecione uma faixa de faturamento mensal.';
            return false;
        }
        return true;
    }

    if (s === 5) {
        const pwd = String(form.password || '');
        const conf = String(form.password_confirmation || '');
        if (pwd.length < 8) {
            wizardStepError.value = 'A senha deve ter no mínimo 8 caracteres.';
            return false;
        }
        if (pwd !== conf) {
            wizardStepError.value = 'A confirmação da senha não confere.';
            return false;
        }
        return true;
    }

    return true;
}

watch(step, () => {
    wizardStepError.value = '';
});

const form = useForm({
    person_type: 'pf',
    name: '',
    email: '',
    coproducer_invite: props.coproducer_invite || '',
    birth_date: '',
    document: '',
    company_name: '',
    legal_representative_cpf: '',
    address_zip: '',
    address_street: '',
    address_number: '',
    address_complement: '',
    address_neighborhood: '',
    address_city: '',
    address_state: '',
    monthly_revenue_range: '',
    password: '',
    password_confirmation: '',
});

const stepTitle = computed(() => {
    const titles = {
        1: 'Dados básicos',
        2: form.person_type === 'pf' ? 'CPF' : 'CNPJ e empresa',
        3: 'Endereço',
        4: 'Faturamento mensal',
        5: 'Senha',
    };
    return titles[step.value] ?? '';
});

const progressPct = computed(() => (step.value / totalSteps) * 100);

watch(
    () => form.email,
    () => {
        emailCheckMsg.value = '';
    }
);

async function checkEmailBlur() {
    const email = (form.email || '').trim();
    if (!email || !email.includes('@')) return;
    try {
        const res = await window.axios.post('/cadastro/validar-email', { email });
        emailCheckMsg.value = res.data?.available
            ? ''
            : 'Este e-mail já está em uso.';
    } catch {
        emailCheckMsg.value = '';
    }
}

async function fetchCep() {
    const raw = digitsOnly(form.address_zip);
    if (raw.length !== 8) {
        cepLookupHint.value = '';
        return;
    }
    cepAbortController?.abort();
    const controller = new AbortController();
    cepAbortController = controller;
    cepLoading.value = true;
    cepLookupHint.value = '';
    try {
        const timeout = setTimeout(() => controller.abort(), 8000);
        const res = await fetch(`https://viacep.com.br/ws/${raw}/json/`, { signal: controller.signal });
        clearTimeout(timeout);
        if (!res.ok) {
            cepLookupHint.value =
                'Não foi possível consultar o CEP. Verifique o número ou preencha logradouro, bairro, cidade e UF manualmente.';
            return;
        }
        const d = await res.json().catch(() => null);
        if (!d || d.erro) {
            cepLookupHint.value =
                'CEP não encontrado na base dos Correios. Confira os dígitos ou preencha o endereço manualmente.';
            return;
        }
        form.address_street = d.logradouro != null ? String(d.logradouro) : '';
        form.address_neighborhood = d.bairro != null ? String(d.bairro) : '';
        form.address_city = d.localidade != null ? String(d.localidade) : '';
        form.address_state = d.uf ? String(d.uf).toUpperCase().slice(0, 2) : '';
        const hasStreet = !!(d.logradouro && String(d.logradouro).trim());
        const hasNbhd = !!(d.bairro && String(d.bairro).trim());
        const hasCity = !!(d.localidade && String(d.localidade).trim());
        if (!hasStreet || !hasNbhd || !hasCity) {
            cepLookupHint.value =
                'Alguns dados não vieram na consulta. Complete logradouro, bairro e cidade manualmente, se necessário.';
        } else {
            cepLookupHint.value = 'Endereço preenchido automaticamente. Ajuste se precisar.';
        }
    } catch (e) {
        if (e.name === 'AbortError') {
            return;
        }
        cepLookupHint.value =
            'Não foi possível consultar o CEP agora. Preencha o endereço manualmente ou tente de novo em instantes.';
    } finally {
        cepLoading.value = false;
    }
}

watch(
    () => digitsOnly(form.address_zip),
    (digits) => {
        if (digits.length !== 8) {
            cepLookupHint.value = '';
            clearTimeout(cepFetchTimer);
            return;
        }
        clearTimeout(cepFetchTimer);
        cepFetchTimer = setTimeout(() => {
            fetchCep();
        }, 400);
    }
);

function maskCpfCnpj(v) {
    const d = String(v).replace(/\D/g, '');
    if (form.person_type === 'pf') {
        return d
            .slice(0, 11)
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    return d
        .slice(0, 14)
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

function maskCpf(v) {
    const d = String(v).replace(/\D/g, '').slice(0, 11);
    return d
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

function maskCep(v) {
    const d = String(v).replace(/\D/g, '').slice(0, 8);
    return d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
}

function prevStep() {
    if (step.value > 1) step.value -= 1;
}

const tagline = 'Rápido, seguro e sem complicação.';

watchEffect(() => {
    const p = primary.value;
    if (typeof document !== 'undefined') {
        document.documentElement.style.setProperty('--color-primary', p);
    }
});

function nextStep() {
    if (step.value < totalSteps) {
        step.value += 1;
    }
}

/** HTML5 `required` em etapas ocultas bloqueava o submit antes de chegar na etapa da senha. */
async function onWizardSubmit() {
    if (step.value === totalSteps) {
        if (!(await validateCurrentStep())) {
            return;
        }
        submitRegistration();
        return;
    }
    if (!(await validateCurrentStep())) {
        return;
    }
    nextStep();
}

/** Enter nos passos 1–4 avança (com validação); no passo 5 o Enter submete o formulário. */
async function onWizardKeydownEnter(e) {
    if (step.value >= totalSteps) {
        return;
    }
    if (e.target.tagName === 'TEXTAREA') {
        return;
    }
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'button' || e.target.closest?.('button')) {
        return;
    }
    e.preventDefault();
    if (!(await validateCurrentStep())) {
        return;
    }
    nextStep();
}

function submitRegistration() {
    form
        .transform((data) => ({
            ...data,
            coproducer_invite: data.coproducer_invite || null,
            document: String(data.document || '').replace(/\D/g, ''),
            legal_representative_cpf: data.person_type === 'pj' ? String(data.legal_representative_cpf || '').replace(/\D/g, '') : null,
            address_zip: String(data.address_zip || '').replace(/\D/g, ''),
            address_state: String(data.address_state || '').toUpperCase().slice(0, 2),
        }))
        .post('/cadastro', { preserveScroll: true });
}
</script>

<template>
    <div class="wl-root flex min-h-screen">
        <div class="flex w-full flex-col justify-center px-8 py-12 lg:w-[32%] lg:min-w-[380px]">
            <div class="text-center">
                <img :src="logoLight" :alt="appName" class="mx-auto mb-8 h-12 w-auto object-contain dark:hidden" />
                <img :src="logoDark" :alt="appName" class="mx-auto mb-8 hidden h-12 w-auto object-contain dark:block" />
                <p class="text-sm font-medium text-teal-600 dark:text-teal-400">{{ tagline }}</p>
            </div>

            <div class="mt-8 rounded-2xl border border-zinc-200 bg-zinc-50/80 p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                <div class="flex items-start justify-between gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
                    <span>Etapa {{ step }} de {{ totalSteps }}</span>
                    <span class="text-[var(--color-primary)]">{{ stepTitle }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :style="{ width: progressPct + '%', backgroundColor: primary }"
                    />
                </div>
                <div class="mt-3 flex justify-center gap-2">
                    <span
                        v-for="n in totalSteps"
                        :key="n"
                        class="h-2 rounded-full transition-all"
                        :class="n === step ? 'w-8' : 'w-2 bg-zinc-300 dark:bg-zinc-600'"
                        :style="n === step ? { backgroundColor: primary } : {}"
                    />
                </div>

                <form class="mt-8 space-y-4" novalidate @submit.prevent="onWizardSubmit" @keydown.enter="onWizardKeydownEnter">
                    <!-- Step 1 -->
                    <div v-show="step === 1" class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tipo de conta</p>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="rounded-xl border-2 p-4 text-left transition"
                                :class="form.person_type === 'pf' ? 'border-[var(--color-primary)]' : 'border-zinc-200 dark:border-zinc-600'"
                                @click="form.person_type = 'pf'"
                            >
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" :style="{ backgroundColor: form.person_type === 'pf' ? primary : '#27272a' }">
                                    <User class="h-5 w-5 text-zinc-900" />
                                </div>
                                <p class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">Pessoa física</p>
                                <p class="text-xs text-zinc-500">Cadastro com CPF</p>
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border-2 p-4 text-left transition"
                                :class="form.person_type === 'pj' ? 'border-[var(--color-primary)]' : 'border-zinc-200 dark:border-zinc-600'"
                                @click="form.person_type = 'pj'"
                            >
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" :style="{ backgroundColor: form.person_type === 'pj' ? primary : '#27272a' }">
                                    <Building2 class="h-5 w-5 text-zinc-900" />
                                </div>
                                <p class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">Pessoa jurídica</p>
                                <p class="text-xs text-zinc-500">Cadastro com CNPJ</p>
                            </button>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">Nome completo</label>
                            <input v-model="form.name" type="text" required class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">E-mail</label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white"
                                @blur="checkEmailBlur"
                            />
                            <p class="mt-1 text-xs text-zinc-500">Ao continuar, verificamos se o e-mail já está em uso.</p>
                            <p v-if="emailCheckMsg" class="mt-1 text-sm text-amber-700 dark:text-amber-400">{{ emailCheckMsg }}</p>
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">Data de nascimento</label>
                            <input v-model="form.birth_date" type="date" required class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            <p class="mt-1 text-xs text-zinc-500">Usamos apenas para validação cadastral e conformidade.</p>
                            <p v-if="form.errors.birth_date" class="mt-1 text-sm text-red-600">{{ form.errors.birth_date }}</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div v-show="step === 2" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">{{ form.person_type === 'pf' ? 'CPF' : 'CNPJ' }}</label>
                            <input
                                :value="form.document"
                                type="text"
                                required
                                class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white"
                                @input="form.document = maskCpfCnpj($event.target.value)"
                            />
                            <p v-if="form.errors.document" class="mt-1 text-sm text-red-600">{{ form.errors.document }}</p>
                        </div>
                        <template v-if="form.person_type === 'pj'">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-zinc-500">Razão social</label>
                                <input v-model="form.company_name" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                                <p v-if="form.errors.company_name" class="mt-1 text-sm text-red-600">{{ form.errors.company_name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-zinc-500">CPF do representante legal</label>
                                <input
                                    :value="form.legal_representative_cpf"
                                    type="text"
                                    class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white"
                                    @input="form.legal_representative_cpf = maskCpf($event.target.value)"
                                />
                                <p v-if="form.errors.legal_representative_cpf" class="mt-1 text-sm text-red-600">{{ form.errors.legal_representative_cpf }}</p>
                            </div>
                        </template>
                    </div>

                    <!-- Step 3 -->
                    <div v-show="step === 3" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">CEP</label>
                            <input
                                :value="form.address_zip"
                                type="text"
                                class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white"
                                placeholder="00000-000"
                                autocomplete="postal-code"
                                @input="form.address_zip = maskCep($event.target.value)"
                                @blur="fetchCep"
                            />
                            <p v-if="cepLoading" class="mt-1 text-xs text-zinc-500">Buscando endereço…</p>
                            <p v-else-if="cepLookupHint" class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">{{ cepLookupHint }}</p>
                            <p v-if="form.errors.address_zip" class="mt-1 text-sm text-red-600">{{ form.errors.address_zip }}</p>
                            <Button
                                v-if="digitsOnly(form.address_zip).length === 8"
                                type="button"
                                variant="outline"
                                class="mt-2 w-full text-xs sm:w-auto"
                                :disabled="cepLoading"
                                @click="fetchCep"
                            >
                                Buscar endereço pelo CEP
                            </Button>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">Logradouro</label>
                            <input v-model="form.address_street" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            <p v-if="form.errors.address_street" class="mt-1 text-sm text-red-600">{{ form.errors.address_street }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-zinc-500">Número</label>
                                <input v-model="form.address_number" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-zinc-500">Complemento</label>
                                <input v-model="form.address_complement" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">Bairro</label>
                            <input v-model="form.address_neighborhood" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-zinc-500">Cidade</label>
                                <input v-model="form.address_city" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-zinc-500">UF</label>
                                <input v-model="form.address_state" maxlength="2" type="text" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 uppercase dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div v-show="step === 4" class="space-y-4">
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-600">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">Faturamento mensal estimado</p>
                            <p class="mt-1 text-xs text-zinc-500">Ajuda a personalizar sua experiência. Escolha a faixa mais próxima da realidade do negócio.</p>
                        </div>
                        <div class="space-y-2">
                            <label
                                v-for="opt in revenue_ranges"
                                :key="opt.value"
                                class="flex cursor-pointer items-center justify-between rounded-xl border-2 px-4 py-3 transition"
                                :class="form.monthly_revenue_range === opt.value ? 'border-[var(--color-primary)]' : 'border-zinc-200 dark:border-zinc-600'"
                            >
                                <span class="text-sm text-zinc-900 dark:text-white">{{ opt.label }}</span>
                                <input v-model="form.monthly_revenue_range" type="radio" class="sr-only" :value="opt.value" />
                                <span
                                    class="flex h-5 w-5 items-center justify-center rounded-full border-2"
                                    :class="form.monthly_revenue_range === opt.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)]' : 'border-zinc-400'"
                                >
                                    <span v-if="form.monthly_revenue_range === opt.value" class="h-2 w-2 rounded-full bg-zinc-900" />
                                </span>
                            </label>
                        </div>
                        <p v-if="form.errors.monthly_revenue_range" class="text-sm text-red-600">{{ form.errors.monthly_revenue_range }}</p>
                    </div>

                    <!-- Step 5 -->
                    <div v-show="step === 5" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">Senha</label>
                            <input v-model="form.password" type="password" required autocomplete="new-password" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-zinc-500">Confirmar senha</label>
                            <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="wl-input mt-1 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-950 dark:text-white" />
                        </div>
                    </div>

                    <p
                        v-if="wizardStepError"
                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200"
                        role="alert"
                    >
                        {{ wizardStepError }}
                    </p>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <Button v-if="step > 1" type="button" variant="outline" class="gap-1" @click="prevStep">
                            <ChevronLeft class="h-4 w-4" />
                            Voltar
                        </Button>
                        <span v-else />
                        <Button type="submit" class="min-w-[120px]" :style="{ backgroundColor: primary, color: '#0a0a0a' }" :disabled="form.processing">
                            {{ step === totalSteps ? (form.processing ? 'Criando…' : 'Criar conta') : 'Continuar' }}
                        </Button>
                    </div>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-zinc-600 dark:text-zinc-400">
                Já tem conta?
                <Link href="/login" class="font-medium text-[var(--color-primary)] hover:underline">Entrar</Link>
            </p>
        </div>

        <div class="relative hidden overflow-hidden bg-zinc-100 dark:bg-zinc-900 lg:flex lg:flex-1 lg:items-center lg:justify-center">
            <img :src="heroImage" alt="" class="h-full w-full object-cover opacity-90 dark:opacity-80" />
        </div>
    </div>
</template>
