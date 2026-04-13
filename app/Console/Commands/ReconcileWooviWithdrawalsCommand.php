<?php

namespace App\Console\Commands;

use App\Gateways\Woovi\WooviDriver;
use App\Models\GatewayCredential;
use App\Models\Withdrawal;
use App\Services\MerchantWithdrawalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReconcileWooviWithdrawalsCommand extends Command
{
    protected $signature = 'withdrawals:reconcile-woovi
                            {--limit=80 : Máximo de saques para checar por execução}
                            {--min-age-minutes=1 : Ignorar registros atualizados há menos de X minutos}
                            {--withdrawal= : ID interno do saque (um registro; ignora min-age)}';

    protected $description = 'Consulta na Woovi saques PIX automáticos ainda pendentes e marca como pagos quando a API já liquidou.';

    public function handle(): int
    {
        if (! Schema::hasTable('withdrawals')) {
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $minAge = max(0, (int) $this->option('min-age-minutes'));
        $onlyId = $this->option('withdrawal');

        $cred = GatewayCredential::resolveForPayment(null, 'woovi');
        if ($cred === null || ! $cred->is_connected) {
            $this->warn('Credencial Woovi (plataforma) não conectada.');

            return self::SUCCESS;
        }

        $credentials = $cred->getDecryptedCredentials();
        if ($credentials === []) {
            return self::SUCCESS;
        }

        if ($onlyId !== null && $onlyId !== '') {
            $w = Withdrawal::query()->find((int) $onlyId);
            if ($w === null) {
                $this->error('Saque não encontrado.');

                return self::FAILURE;
            }
            if ($w->status !== 'pending' || $w->payout_provider !== 'woovi') {
                $this->warn('Saque ignorado (não está pending/woovi).');

                return self::SUCCESS;
            }
            $tx = trim((string) $w->payout_external_id);
            if ($tx === '') {
                $this->error('Saque sem payout_external_id; não é possível consultar na Woovi.');

                return self::FAILURE;
            }
            $driver = new WooviDriver;
            $apiStatus = $driver->getTransferStatus($tx, $credentials);
            if ($apiStatus === 'paid') {
                MerchantWithdrawalService::markPaid($w->fresh());
                $this->info('Saque marcado como pago.');
            } else {
                $this->warn('API retornou status: '.($apiStatus ?? 'null').' (esperado paid após liquidação).');
            }

            return self::SUCCESS;
        }

        $q = Withdrawal::query()
            ->where('status', 'pending')
            ->where('payout_provider', 'woovi')
            ->whereNotNull('payout_external_id')
            ->where('payout_external_id', '!=', '');

        if ($minAge > 0) {
            $q->where('updated_at', '<=', now()->subMinutes($minAge));
        }

        $rows = $q->orderBy('id')->limit($limit)->get();

        $driver = new WooviDriver;
        $paid = 0;

        foreach ($rows as $withdrawal) {
            $tx = trim((string) $withdrawal->payout_external_id);
            if ($tx === '') {
                continue;
            }

            try {
                $apiStatus = $driver->getTransferStatus($tx, $credentials);
            } catch (\Throwable) {
                $apiStatus = null;
            }

            if ($apiStatus === 'paid') {
                MerchantWithdrawalService::markPaid($withdrawal->fresh());
                $paid++;
            }
        }

        if ($paid > 0) {
            $this->info("Marcados como pagos: {$paid}.");
        }

        return self::SUCCESS;
    }
}
