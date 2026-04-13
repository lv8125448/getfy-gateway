<?php

namespace App\Console\Commands;

use App\Services\SettlementReleaseService;
use Illuminate\Console\Command;

class SettlementReleaseCommand extends Command
{
    protected $signature = 'settlement:release {--limit=500 : Max pending rows to scan}';

    protected $description = 'Libera créditos de vendas (D+N / reserva) da carteira pendente para disponível';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $n = SettlementReleaseService::releaseDue($limit);
        $this->info("Liberações processadas: {$n}");

        return self::SUCCESS;
    }
}
