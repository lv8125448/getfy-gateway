<?php

namespace App\Services\Woovi;

use App\Gateways\Woovi\WooviDriver;
use App\Models\GatewayCredential;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\EffectiveMerchantFees;
use App\Services\Payout\GatewayPayoutEconomics;
use App\Services\Payout\PayoutUserSettings;

class WooviPayoutService
{
    private WooviDriver $driver;

    public function __construct(?WooviDriver $driver = null)
    {
        $this->driver = $driver ?? new WooviDriver;
    }

    /**
     * @return array{
     *     ok: bool,
     *     pending?: bool,
     *     transaction_id?: string|null,
     *     error?: string
     * }
     */
    public function sendWithdrawalToPix(Withdrawal $withdrawal, User $owner): array
    {
        $credential = GatewayCredential::resolveForPayment(null, 'woovi');
        if ($credential === null) {
            return ['ok' => false, 'error' => 'Saque automático não configurado pela plataforma (Woovi).'];
        }
        $credentials = $credential->getDecryptedCredentials();
        if (trim((string) ($credentials['app_id'] ?? '')) === '' || trim((string) ($credentials['from_pix_key'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Configure AppID e chave PIX de origem (Woovi) nas integrações da plataforma.'];
        }

        $net = (float) $withdrawal->net_amount;
        if ($net <= 0) {
            return ['ok' => false, 'error' => 'Valor líquido do saque inválido.'];
        }

        $economics = GatewayPayoutEconomics::fromCredentialsArray('woovi', $credentials);
        $requiredNet = $economics['required_min_net'];
        $minCents = (int) max(1, (int) round($requiredNet * 100));
        $apiAmount = GatewayPayoutEconomics::transferAmountBrlForApi($net, $economics['admin_fee_payout_brl']);
        $amountCents = (int) round($net * 100);
        if ($amountCents < $minCents) {
            $tenantId = (int) $withdrawal->tenant_id;
            $minGross = EffectiveMerchantFees::minimumWithdrawalGrossForTargetNet($tenantId, $requiredNet);
            $msg = $minGross !== null
                ? 'O valor mínimo do saque é R$ '
                    .number_format($minGross, 2, ',', '.').' (valor total a solicitar).'
                : 'O valor solicitado é inferior ao mínimo permitido.';

            return ['ok' => false, 'error' => $msg];
        }

        $settings = is_array($owner->payout_settings) ? $owner->payout_settings : [];
        $pixKey = PayoutUserSettings::pixKey($settings);
        if ($pixKey === '') {
            return ['ok' => false, 'error' => 'Cadastre a chave PIX de destino no Financeiro antes de solicitar o saque.'];
        }

        $transferCents = (int) max(1, (int) round($apiAmount * 100));
        $result = $this->driver->createTransfer($credentials, $transferCents, $pixKey);
        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Falha ao processar o saque na Woovi.'];
        }

        $tid = $result['transaction_id'] ?? $result['correlation_id'] ?? null;

        return [
            'ok' => true,
            'pending' => true,
            'transaction_id' => is_string($tid) && $tid !== '' ? $tid : null,
        ];
    }
}
