<?php

namespace App\Console\Commands;

use App\Services\PluginStoreService;
use Illuminate\Console\Command;

class TestPluginStoreConnection extends Command
{
    protected $signature = 'plugin-store:test';

    protected $description = 'Testa a conexão com a loja de plugins (PLUGIN_STORE_URL)';

    public function handle(): int
    {
        $store = app(PluginStoreService::class);
        $url = config('services.plugin_store.url');

        if (! $store->isConfigured()) {
            $this->error('PLUGIN_STORE_URL não está definido no .env.');
            $this->line('Exemplo: PLUGIN_STORE_URL=http://plugins-getfy.test');

            return 1;
        }

        $this->info('URL da loja: ' . $url);
        $this->line('Chamando: ' . $url . '/api/v1/plugins');
        $this->newLine();

        $response = $store->listPlugins();
        $data = $response['data'] ?? [];
        $error = $response['error'] ?? null;

        if ($error) {
            $this->error('Falha: ' . $error);
            $this->newLine();
            $this->line('Verifique:');
            $this->line('  1. O plugins-getfy está rodando em ' . $url);
            $this->line('  2. No navegador, ' . $url . '/api/v1/plugins retorna JSON');
            $this->line('  3. Se usar Docker, o host precisa resolver (ex.: host.docker.internal)');

            return 1;
        }

        $count = is_array($data) ? count($data) : 0;
        $this->info('OK. Plugins encontrados: ' . $count);

        if ($count > 0) {
            $this->table(
                ['Nome', 'Slug', 'Preço'],
                array_slice(array_map(fn ($p) => [$p['name'] ?? '', $p['slug'] ?? '', ($p['price'] ?? 0) == 0 ? 'Grátis' : 'R$ ' . ($p['price'] ?? 0)], $data), 0, 5)
            );
        }

        return 0;
    }
}
