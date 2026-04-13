<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { Upload, FileText, CheckCircle2, BadgeCheck } from 'lucide-vue-next';

const props = defineProps({
    person_type: { type: String, default: 'pf' },
    kyc_status: { type: String, default: 'not_submitted' },
    rejection_reason: { type: String, default: null },
    /** Quando true, omite título principal (uso na aba Financeiro). */
    embedded: { type: Boolean, default: false },
});

const isPj = computed(() => props.person_type === 'pj');

/** Aguardando análise — sem novo upload. */
const isPendingReview = computed(() => props.kyc_status === 'pending_review');

/** Aprovado pela plataforma — não exibe formulário de envio. */
const isApproved = computed(() => props.kyc_status === 'approved');

/** Qualquer estado final/visualização sem upload (pendente ou aprovado). */
const isReadOnlyKyc = computed(() => isPendingReview.value || isApproved.value);

const form = useForm({
    rg_front: null,
    rg_back: null,
    company_document: null,
});

function onFile(field, event) {
    const f = event.target.files?.[0];
    form[field] = f || null;
}

function submit() {
    form.post('/kyc', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const inputFileClass =
    'block w-full cursor-pointer rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 file:mr-3 file:rounded file:border-0 file:bg-zinc-100 file:px-3 file:py-1 file:text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:file:bg-zinc-700';

const fileAccept =
    'image/jpeg,image/jpg,image/png,image/webp,image/gif,image/heic,image/heif,application/pdf,.pdf,.jpg,.jpeg,.png,.webp,.gif,.heic,.heif';
</script>

<template>
    <div class="space-y-6" :class="embedded ? '' : 'mx-auto max-w-2xl'">
        <div v-if="!embedded && !isReadOnlyKyc">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Verificação de identidade (KYC)</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                Envie <strong>imagem</strong> (foto legível) ou <strong>PDF</strong>, até 20 MB por arquivo. Aceitos: JPG, PNG, WebP, GIF, HEIC/HEIF (fotos de celular) ou PDF.
            </p>
        </div>
        <div v-else-if="!embedded && isReadOnlyKyc">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Verificação de identidade (KYC)</h1>
        </div>
        <div v-else-if="embedded && !isReadOnlyKyc">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Documentos para verificação</h3>
            <p class="mt-1 text-xs text-zinc-500">
                Imagem ou PDF (JPG, PNG, WebP, GIF, HEIC/HEIF ou PDF), até 20 MB por arquivo.
            </p>
        </div>

        <div
            v-if="rejection_reason && !isReadOnlyKyc"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            <p class="font-medium">Última análise foi rejeitada:</p>
            <p class="mt-1">{{ rejection_reason }}</p>
        </div>

        <div
            v-if="isPendingReview"
            class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 px-5 py-6 text-center shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/35"
        >
            <div class="flex justify-center">
                <CheckCircle2 class="h-12 w-12 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
            </div>
            <h3 class="mt-4 text-base font-semibold text-emerald-950 dark:text-emerald-100">Documentos enviados</h3>
            <p class="mt-2 text-sm text-emerald-900/90 dark:text-emerald-200/95">
                Recebemos seus arquivos. Eles estão <strong>em análise</strong> pela equipe da plataforma. Você será avisado quando a verificação for concluída.
            </p>
        </div>

        <div
            v-else-if="isApproved"
            class="rounded-2xl border border-[var(--color-primary)]/40 bg-[var(--color-primary)]/10 px-5 py-6 text-center shadow-sm dark:border-[var(--color-primary)]/35 dark:bg-[var(--color-primary)]/15"
        >
            <div class="flex justify-center">
                <BadgeCheck class="h-12 w-12 text-[var(--color-primary)]" aria-hidden="true" />
            </div>
            <h3 class="mt-4 text-base font-semibold text-zinc-900 dark:text-white">Verificação aprovada</h3>
            <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">
                Sua identidade foi <strong>confirmada</strong> pela plataforma. Não é necessário enviar novos documentos.
            </p>
        </div>

        <form
            v-else
            class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/40"
            @submit.prevent="submit"
        >
            <div>
                <h2 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                    <Upload class="h-4 w-4 text-[var(--color-primary)]" />
                    RG — frente e verso
                </h2>
                <p class="mt-1 text-xs text-zinc-500">Documento de identidade do responsável.</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium uppercase text-zinc-500">Frente</label>
                        <input type="file" :accept="fileAccept" :class="inputFileClass" @change="onFile('rg_front', $event)" />
                        <p v-if="form.errors.rg_front" class="mt-1 text-sm text-red-600">{{ form.errors.rg_front }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium uppercase text-zinc-500">Verso</label>
                        <input type="file" :accept="fileAccept" :class="inputFileClass" @change="onFile('rg_back', $event)" />
                        <p v-if="form.errors.rg_back" class="mt-1 text-sm text-red-600">{{ form.errors.rg_back }}</p>
                    </div>
                </div>
            </div>

            <div v-if="isPj" class="border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                    <FileText class="h-4 w-4 text-[var(--color-primary)]" />
                    Empresa
                </h2>
                <p class="mt-1 text-xs text-zinc-500">
                    Envie <strong>um arquivo</strong>: cartão CNPJ <strong>ou</strong> contrato social (o que preferir). Imagem ou PDF, mesmos formatos acima.
                </p>
                <div class="mt-3 max-w-xl">
                    <label class="block text-xs font-medium uppercase text-zinc-500">Documento da empresa (CNPJ ou contrato)</label>
                    <input type="file" :accept="fileAccept" :class="inputFileClass" @change="onFile('company_document', $event)" />
                    <p v-if="form.errors.company_document" class="mt-1 text-sm text-red-600">{{ form.errors.company_document }}</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Enviando…' : 'Enviar para análise' }}
                </Button>
            </div>
        </form>
    </div>
</template>
