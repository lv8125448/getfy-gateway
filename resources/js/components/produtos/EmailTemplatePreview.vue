<script setup>
import { computed } from 'vue';

const props = defineProps({
  logoUrl: { type: String, default: '' },
  subject: { type: String, default: '' },
  bodyHtml: { type: String, default: '' },
  fromName: { type: String, default: '' },
});

const SAMPLE = {
  nome_cliente: 'Maria Silva',
  nome_produto: 'Meu Curso',
  link_acesso: 'https://exemplo.com/m/xxxxx',
  email_cliente: 'maria@exemplo.com',
};

function replacePlaceholders(text) {
  if (!text || typeof text !== 'string') return '';
  return text
    .replace(/\{nome_cliente\}/g, SAMPLE.nome_cliente)
    .replace(/\{nome_produto\}/g, SAMPLE.nome_produto)
    .replace(/\{link_acesso\}/g, SAMPLE.link_acesso)
    .replace(/\{email_cliente\}/g, SAMPLE.email_cliente);
}

const previewSubject = computed(() => replacePlaceholders(props.subject));
const previewBodyHtml = computed(() => replacePlaceholders(props.bodyHtml));
</script>

<template>
  <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 dark:border-zinc-600 dark:bg-zinc-900/80 overflow-hidden shadow-inner">
    <div class="border-b border-zinc-200 bg-white px-3 py-2 flex items-center gap-2 dark:border-zinc-600 dark:bg-zinc-800">
      <span class="text-[10px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500 font-medium">Preview</span>
      <span class="text-zinc-300 dark:text-zinc-600">·</span>
      <span class="text-xs text-zinc-500 dark:text-zinc-400">dados de exemplo</span>
    </div>
    <div class="p-3 bg-white dark:bg-zinc-800/90 min-h-[200px] overflow-auto max-h-[520px]">
      <div class="text-xs text-zinc-500 dark:text-zinc-400 space-y-1 mb-3 pb-2 border-b border-zinc-100 dark:border-zinc-700">
        <div v-if="fromName" class="flex gap-2">
          <span class="text-zinc-400 shrink-0">De:</span>
          <span class="text-zinc-700 dark:text-zinc-300 truncate">{{ fromName }}</span>
        </div>
        <div v-if="previewSubject" class="flex gap-2">
          <span class="text-zinc-400 shrink-0">Assunto:</span>
          <span class="text-zinc-800 dark:text-zinc-200 font-medium truncate">{{ previewSubject }}</span>
        </div>
      </div>
      <div v-if="logoUrl" class="mb-3 flex justify-center">
        <img :src="logoUrl" alt="Logo" class="max-h-10 w-auto object-contain mx-auto" @error="($e) => $e.target.style.display = 'none'" />
      </div>
      <div
        class="email-preview-body text-sm text-zinc-700 dark:text-zinc-300 font-sans max-w-none break-words"
        v-html="previewBodyHtml"
      />
    </div>
  </div>
</template>

<style scoped>
.email-preview-body :deep(table) { width: 100%; max-width: 100%; }
.email-preview-body :deep(a) { color: #0ea5e9; text-decoration: none; }
.email-preview-body :deep(p) { margin: 0 0 0.75em; line-height: 1.5; }
.email-preview-body :deep(strong) { font-weight: 600; }
</style>
