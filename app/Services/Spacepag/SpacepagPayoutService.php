<?php

namespace App\Services\Spacepag;

use App\Gateways\Spacepag\SpacepagDriver;
use App\Models\GatewayCredential;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\EffectiveMerchantFees;
use App\Services\Payout\GatewayPayoutEconomics;
use App\Services\Payout\PayoutUserSettings;
use Illuminate\Support\Facades\URL;

class SpacepagPayoutService
{
    private SpacepagDriver $driver;

    public function __construct(?SpacepagDriver $driver = null)
    {
        $this->driver = $driver ?? new SpacepagDriver();
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
        $credential = GatewayCredential::resolveForPayment(null, 'spacepag');
        if ($credential === null) {
            return ['ok' => false, 'error' => 'Saque automático não configurado pela plataforma.'];
        }
        $credentials = $credential->getDecryptedCredentials();
        if (empty($credentials['public_key'] ?? null) || empty($credentials['secret_key'] ?? null)) {
            return ['ok' => false, 'error' => 'Configuração de saque incompleta. Contate o suporte.'];
        }

        $net = (float) $withdrawal->net_amount;
        if ($net <= 0) {
            return ['ok' => false, 'error' => 'Valor líquido do saque inválido.'];
        }

        $economics = GatewayPayoutEconomics::fromCredentialsArray('spacepag', $credentials);
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
        $pixKeyType = PayoutUserSettings::pixKeyType($settings);
        $receiverName = isset($settings['receiver_name']) ? trim((string) $settings['receiver_name']) : '';
        $receiverDocument = isset($settings['receiver_document']) ? trim((string) $settings['receiver_document']) : '';
        $receiverEmail = isset($settings['receiver_email']) ? trim((string) $settings['receiver_email']) : '';

        if ($pixKey === '' || $pixKeyType === '' || $receiverName === '' || $receiverDocument === '' || $receiverEmail === '') {
            return ['ok' => false, 'error' => 'Complete os dados de PIX e recebedor no Financeiro antes de solicitar o saque.'];
        }

        $externalId = 'getfy-withdrawal-'.$withdrawal->id;
        $postback = self::resolveWebhookPostbackUrl($credentials);

        $body = [
            'amount' => round($apiAmount, 2),
            'pix_key' => $pixKey,
            'pix_key_type' => strtolower($pixKeyType),
            'receiver' => [
                'name' => $receiverName,
                'document' => $receiverDocument,
                'email' => $receiverEmail,
            ],
            'external_id' => $externalId,
            'postback' => $postback,
        ];

        $result = $this->driver->createPixOut($credentials, $body);
        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Falha ao processar o saque. Tente novamente ou contate o suporte.'];
        }

        return [
            'ok' => true,
            'pending' => true,
            'transaction_id' => $result['transaction_id'] ?? null,
        ];
    }

    /**
     * Postback da Spacepag (/pixout) é obrigatório e deve ser alcançável publicamente em produção.
     * GETFY_WEBHOOK_PUBLIC_URL ou credenciais webhook_postback_base_url evitam APP_URL local/HTTP ser descartado no driver.
     *
     * @param  array<string, mixed>  $credentials
     */
    private static function resolveWebhookPostbackUrl(array $credentials): string
    {
        $path = route('webhooks.spacepag', [], false);
        if (! is_string($path) || $path === '') {
            $path = '/webhooks/gateways/spacepag';
        }

        $base = trim((string) (config('getfy.webhook_public_url') ?? ''));
        if ($base !== '') {
            return $base.$path;
        }

        $credBase = trim((string) ($credentials['webhook_postback_base_url'] ?? $credentials['postback_base_url'] ?? ''));
        if ($credBase !== '') {
            return rtrim($credBase, '/').$path;
        }

        return URL::to(route('webhooks.spacepag', [], true));
    }
}
