<?php

namespace App\Services\CajuPay;

use App\Models\GatewayCredential;
use App\Models\Withdrawal;
use App\Services\EffectiveMerchantFees;
use App\Services\Payout\GatewayPayoutEconomics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CajuPayPayoutService
{
    /**
     * @return array{ok: bool, external_id?: string, error?: string, status?: int}
     */
    public function sendWithdrawalToPixKey(Withdrawal $withdrawal, string $pixKeyId): array
    {
        $credential = GatewayCredential::resolveForPayment(null, 'cajupay');
        if ($credential === null) {
            return ['ok' => false, 'error' => 'CajuPay não configurado na plataforma (Integrações > Gateways).'];
        }
        $credentials = $credential->getDecryptedCredentials();
        if (empty($credentials['public_key'] ?? null) || empty($credentials['secret_key'] ?? null)) {
            return ['ok' => false, 'error' => 'Credenciais CajuPay incompletas.'];
        }

        $net = (float) $withdrawal->net_amount;
        if ($net <= 0) {
            return ['ok' => false, 'error' => 'Valor líquido do saque inválido.'];
        }

        $economics = GatewayPayoutEconomics::fromCredentialsArray('cajupay', $credentials);
        $requiredNet = $economics['required_min_net'];
        $minCents = (int) max(1, (int) round($requiredNet * 100));

        $netCents = (int) round($net * 100);
        if ($netCents < $minCents) {
            $tenantId = (int) $withdrawal->tenant_id;
            $minGross = EffectiveMerchantFees::minimumWithdrawalGrossForTargetNet($tenantId, $requiredNet);
            $msg = $minGross !== null
                ? 'O valor mínimo do saque é R$ '
                    .number_format($minGross, 2, ',', '.').' (valor total a solicitar).'
                : 'O valor solicitado é inferior ao mínimo permitido.';

            return ['ok' => false, 'error' => $msg];
        }

        $apiAmount = GatewayPayoutEconomics::transferAmountBrlForApi($net, $economics['admin_fee_payout_brl']);
        $amountCents = (int) round($apiAmount * 100);

        $http = $this->httpForCredentials($credentials);

        $idempotencyKey = Str::limit('getfy-withdrawal-'.$withdrawal->id, 200, '');

        $body = [
            'amount_cents' => $amountCents,
            'currency' => 'BRL',
            'wallet_kind' => 'main',
            'destination' => ['method' => 'pix_saved_key'],
            'pix_key_id' => $pixKeyId,
        ];

        $response = $http
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/api/payouts', $body);

        if ($response->successful()) {
            $json = $response->json();
            $ext = null;
            if (is_array($json)) {
                $ext = $json['id'] ?? $json['payout_id'] ?? $json['payment_id'] ?? $json['uuid'] ?? null;
                if ($ext === null && isset($json['data']) && is_array($json['data'])) {
                    $d = $json['data'];
                    $ext = $d['id'] ?? $d['payout_id'] ?? null;
                }
            }
            if (! is_string($ext) || $ext === '') {
                $ext = trim((string) $response->body());
            }
            if (strlen($ext) > 120) {
                $ext = substr($ext, 0, 120).'…';
            }

            Log::info('CajuPayPayoutService: payout aceito pela API.', [
                'withdrawal_id' => $withdrawal->id,
                'http_status' => $response->status(),
                'external_ref' => $ext,
            ]);

            return ['ok' => true, 'external_id' => Str::limit($ext, 80, ''), 'status' => $response->status()];
        }

        $msg = $response->body();
        if (strlen($msg) > 500) {
            $msg = substr($msg, 0, 500).'…';
        }
        if ($response->status() === 403) {
            if (str_contains(strtolower($msg), 'kyc')) {
                $msg = 'Conta CajuPay com KYC pendente ou saques bloqueados. Conclua no painel CajuPay.';
            } elseif (str_contains(strtolower($msg), 'scope') || str_contains(strtolower($msg), 'permiss')) {
                $msg = 'Chave de API sem permissão de saque (payouts). Crie uma chave com escopo payouts.write no painel CajuPay.';
            }
        }

        Log::warning('CajuPayPayoutService: payout recusado.', [
            'withdrawal_id' => $withdrawal->id,
            'http_status' => $response->status(),
        ]);

        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'Erro HTTP '.$response->status(), 'status' => $response->status()];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function httpForCredentials(array $credentials): \Illuminate\Http\Client\PendingRequest
    {
        $public = trim((string) ($credentials['public_key'] ?? ''));
        $secret = trim((string) ($credentials['secret_key'] ?? ''));

        $override = isset($credentials['base_url']) ? trim((string) $credentials['base_url']) : '';
        $base = $override !== ''
            ? rtrim($override, '/')
            : rtrim((string) config('services.cajupay.base_url', 'https://api.cajupay.com.br'), '/');

        return Http::acceptJson()
            ->asJson()
            ->timeout(35)
            ->withOptions(['connect_timeout' => 15])
            ->baseUrl($base)
            ->withHeaders([
                'X-API-Key' => $public,
                'X-API-Secret' => $secret,
            ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<int, array<string, mixed>>
     */
    public static function listPixKeys(array $credentials): array
    {
        $public = trim((string) ($credentials['public_key'] ?? ''));
        $secret = trim((string) ($credentials['secret_key'] ?? ''));
        if ($public === '' || $secret === '') {
            return [];
        }
        $base = rtrim((string) config('services.cajupay.base_url', 'https://api.cajupay.com.br'), '/');
        $response = Http::acceptJson()
            ->timeout(25)
            ->baseUrl($base)
            ->withHeaders([
                'X-API-Key' => $public,
                'X-API-Secret' => $secret,
            ])
            ->get('/api/pix-keys');

        if (! $response->successful()) {
            return [];
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array{label: string, pix_key_type: string, pix_key: string, is_default?: bool}  $payload
     * @return array{ok: bool, id?: string, error?: string}
     */
    public static function createPixKey(array $credentials, array $payload): array
    {
        $public = trim((string) ($credentials['public_key'] ?? ''));
        $secret = trim((string) ($credentials['secret_key'] ?? ''));
        if ($public === '' || $secret === '') {
            return ['ok' => false, 'error' => 'Credenciais ausentes.'];
        }
        $base = rtrim((string) config('services.cajupay.base_url', 'https://api.cajupay.com.br'), '/');
        $idempotencyKey = Str::limit('getfy-pixkey-'.sha1(($payload['pix_key'] ?? '').$payload['pix_key_type']), 200, '');

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->baseUrl($base)
            ->withHeaders([
                'X-API-Key' => $public,
                'X-API-Secret' => $secret,
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->post('/api/pix-keys', $payload);

        if ($response->successful()) {
            $json = $response->json();
            $id = is_array($json) ? ($json['id'] ?? $json['pix_key_id'] ?? null) : null;

            return ['ok' => true, 'id' => is_string($id) ? $id : null];
        }

        $msg = $response->body();
        if (strlen($msg) > 300) {
            $msg = substr($msg, 0, 300).'…';
        }

        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'Erro ao cadastrar chave PIX.'];
    }
}
