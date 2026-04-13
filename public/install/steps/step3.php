<?php
$db = $_POST;
if (empty($db['db_database']) && empty($db['db_host'])) {
    $db = $_GET;
}
if (!is_array($db)) {
    $db = [];
}
// Redirect to step 2 if no DB data (e.g. direct access to step 3)
if (empty($db['db_database']) && empty($db['db_host'])) {
    header('Location: ?step=2');
    exit;
}
$db_host = htmlspecialchars($db['host'] ?? $db['db_host'] ?? '127.0.0.1');
$db_port = htmlspecialchars((string)($db['port'] ?? $db['db_port'] ?? '3306'));
$db_database = htmlspecialchars($db['database'] ?? $db['db_database'] ?? '');
$db_username = htmlspecialchars($db['username'] ?? $db['db_username'] ?? '');
$db_password = $db['password'] ?? $db['db_password'] ?? '';
?>
<form id="form-app" class="space-y-4">
    <input type="hidden" name="db_host" value="<?= $db_host ?>">
    <input type="hidden" name="db_port" value="<?= $db_port ?>">
    <input type="hidden" name="db_database" value="<?= $db_database ?>">
    <input type="hidden" name="db_username" value="<?= $db_username ?>">
    <input type="hidden" name="db_password" value="<?= htmlspecialchars($db_password) ?>">
    <div>
        <label for="app_url" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">URL da aplicação</label>
        <input type="url" id="app_url" name="app_url" value="<?= htmlspecialchars(((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) ?>"
            class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3"
            placeholder="https://seudominio.com">
    </div>
    <div>
        <label for="app_env" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Ambiente</label>
        <select id="app_env" name="app_env" class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
            <option value="production">Produção</option>
            <option value="local">Desenvolvimento</option>
        </select>
    </div>
    <div>
        <label for="session_driver" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sessão / Fila</label>
        <select id="session_driver" name="session_driver" class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
            <option value="file">Arquivo (recomendado para início)</option>
            <option value="redis">Redis</option>
        </select>
        <p class="mt-1 text-xs text-zinc-500">Use "Arquivo" se Redis não estiver configurado.</p>
    </div>
    <div class="flex gap-3 pt-2">
        <a href="?step=2" class="rounded-xl border border-zinc-300 dark:border-zinc-600 px-4 py-3 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800">Voltar</a>
        <button type="submit" id="btn-install" class="flex-1 rounded-xl bg-[#c8fa64] text-zinc-900 font-semibold py-3 px-4 hover:opacity-90">
            Instalar
        </button>
    </div>
</form>
<div id="install-progress-wrap" class="hidden space-y-4">
    <div class="flex items-center gap-3 text-zinc-600 dark:text-zinc-400">
        <svg class="animate-spin h-6 w-6 text-[#c8fa64]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span id="install-status">Iniciando instalação...</span>
        <span class="install-step-hint block text-xs text-zinc-500 dark:text-zinc-400 mt-1"></span>
    </div>
    <div class="bg-zinc-200 dark:bg-zinc-700 rounded-full h-2 overflow-hidden">
        <div id="install-bar" class="h-full bg-[#c8fa64] transition-all duration-300" style="width: 0%"></div>
    </div>
    <pre id="install-log" class="text-xs bg-zinc-900 text-zinc-300 p-4 rounded-xl max-h-48 overflow-y-auto hidden"></pre>
    <div id="install-error" class="hidden p-4 rounded-xl bg-red-500/10 text-red-600 dark:text-red-400 text-sm"></div>
    <div id="install-success" class="hidden">
        <p class="text-green-600 dark:text-green-400 font-medium">Instalação concluída!</p>
        <a href="/" class="mt-4 inline-flex rounded-xl bg-[#c8fa64] text-zinc-900 font-semibold py-3 px-6 hover:opacity-90">
            Continuar para a plataforma
        </a>
    </div>
</div>
