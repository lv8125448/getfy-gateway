<?php

namespace App\Jobs;

use App\Gateways\Woovi\WooviDriver;
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
 * Consulta GET /api/v1/transaction/{id} na Woovi até o saque constar como pago.
 */
class ReconcileWooviWithdrawalJob implements ShouldQueue
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
        if ($withdrawal === null || $withdrawal->status !== 'pending' || $withdrawal->payout_provider !== 'woovi') {
            return;
        }

        $tx = trim((string) $withdrawal->payout_external_id);
        if ($tx === '') {
            return;
        }

        $cred = GatewayCredential::resolveForPayment(null, 'woovi');
        if ($cred === null || ! $cred->is_connected) {
            $this->maybeReleaseForRetry();

            return;
        }

        $credentials = $cred->getDecryptedCredentials();
        if ($credentials === []) {
            return;
        }

        $driver = new WooviDriver;
        try {
            $apiStatus = $driver->getTransferStatus($tx, $credentials);
        } catch (\Throwable $e) {
            Log::warning('ReconcileWooviWithdrawalJob: falha na consulta', [
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
            Log::info('ReconcileWooviWithdrawalJob: encerrando tentativas', ['withdrawal_id' => $this->withdrawalId]);

            return;
        }

        $this->release(120);
    }
}
