<form id="form-database" action="?step=3" method="POST" class="space-y-4">
    <div>
        <label for="db_host" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Host do banco</label>
        <input type="text" id="db_host" name="db_host" value="127.0.0.1" required
            class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
    </div>
    <div>
        <label for="db_port" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Porta</label>
        <input type="number" id="db_port" name="db_port" value="3306"
            class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
    </div>
    <div>
        <label for="db_database" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome do banco</label>
        <input type="text" id="db_database" name="db_database" required placeholder="getfy"
            class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
    </div>
    <div>
        <label for="db_username" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Usuário</label>
        <input type="text" id="db_username" name="db_username" required
            class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
    </div>
    <div>
        <label for="db_password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Senha</label>
        <input type="password" id="db_password" name="db_password"
            class="mt-1.5 block w-full rounded-xl border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-4 py-3">
    </div>
    <div id="db-test-result" class="hidden text-sm rounded-lg p-3"></div>
    <div class="flex gap-3">
        <button type="button" id="btn-test-db" class="rounded-xl border border-zinc-300 dark:border-zinc-600 px-4 py-3 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800">
            Testar conexão
        </button>
        <a href="?step=1" class="rounded-xl border border-zinc-300 dark:border-zinc-600 px-4 py-3 text-sm font-medium hover:bg-zinc-100 dark:hover:bg-zinc-800">Voltar</a>
        <button type="submit" class="flex-1 rounded-xl bg-[#c8fa64] text-zinc-900 font-semibold py-3 px-4 hover:opacity-90">
            Próximo
        </button>
    </div>
</form>
