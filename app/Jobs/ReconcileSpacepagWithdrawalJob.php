<?php

namespace App\Jobs;

use App\Gateways\Spacepag\SpacepagDriver;
use App\Models\GatewayCredential;
use App\Models\Withdrawal;
use App\Services\MerchantWithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Consulta GET /transactions/payment na Spacepag até o saque constar como pago.
 * Disparado após pixout bem-sucedido (pending). Com fila database/redis + queue:work, renova sozinho.
 * Com QUEUE_CONNECTION=sync só há uma tentativa por disparo — use cron ou comando manual como reserva.
 */
class ReconcileSpacepagWithdrawalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 90;

    public function __construct(public int $withdrawalId) {}

    public function handle(): void
    {
        $withdrawal = Withdrawal::query()->find($this->withdrawalId);
        if ($withdrawal === null || $withdrawal->status !== 'pending' || $withdrawal->payout_provider !== 'spacepag') {
            return;
        }

        $tx = trim((string) $withdrawal->payout_external_id);
        if ($tx === '') {
            return;
        }

        $cred = GatewayCredential::resolveForPayment(null, 'spacepag');
        if ($cred === null || ! $cred->is_connected) {
            $this->maybeReleaseForRetry();

            return;
        }

        $credentials = $cred->getDecryptedCredentials();
        if ($credentials === []) {
            return;
        }

        $driver = new SpacepagDriver;
        try {
            $apiStatus = $driver->getPixOutTransactionStatus($tx, $credentials);
        } catch (\Throwable $e) {
            Log::warning('ReconcileSpacepagWithdrawalJob: falha na consulta', [
                'withdrawal_id' => $this->withdrawalId,
                'message' => $e->getMessage(),
            ]);
            $this->maybeReleaseForRetry();

            return;
        }

        if ($apiStatus === 'paid') {
            MerchantWithdrawalService::markPaid($withdrawal->fresh());

            return;
        }

        $this->maybeReleaseForRetry();
    }

    private function maybeReleaseForRetry(): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        if ($this->attempts() >= 36) {
            Log::info('ReconcileSpacepagWithdrawalJob: encerrando tentativas', ['withdrawal_id' => $this->withdrawalId]);

            return;
        }

        $this->release(120);
    }
}
