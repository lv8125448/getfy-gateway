<?php

namespace App\Gateways\Woovi;

use App\Gateways\Contracts\GatewayDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WooviDriver implements GatewayDriver
{
    private const DEFAULT_BASE = 'https://api.woovi.com';

    /** Host da API no ambiente de testes (AppID de sandbox não funciona em api.woovi.com). */
    private const DEFAULT_SANDBOX_BASE = 'https://api.woovi-sandbox.com';

    /**
     * Valida o AppID com GET leve (empresa ou listagem de cobranças).
     */
    public function testConnection(array $credentials): bool
    {
        $appId = $this->appId($credentials);
        if ($appId === '') {
            return false;
        }

        $base = rtrim($this->baseUrl($credentials), '/');
        $paths = [
            '/api/v1/company',
            '/api/v1/charge',
            '/api/openpix/v1/charge',
        ];

        foreach ($paths as $path) {
            $url = $base.$path;
            $query = str_ends_with($path, '/company') ? [] : ['limit' => 1];
            try {
                $response = Http::timeout(15)
                    ->connectTimeout(10)
                    ->withHeaders($this->authHeaders($credentials))
                    ->get($url, $query);
            } catch (\Throwable) {
                continue;
            }

            if ($response->status() === 401) {
                return false;
            }

            if ($response->successful()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{name?: string, document?: string, email?: string}  $consumer
     * @return array{transaction_id: string, qrcode?: string|null, copy_paste?: string|null, raw?: array}
     */
    public function createPixPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $postbackUrl
    ): array {
        $appId = $this->appId($credentials);
        if ($appId === '') {
            throw new \RuntimeException('Woovi: configure o AppID nas credenciais.');
        }

        $correlationID = (string) Str::uuid();
        $valueCents = max(1, (int) round($amount * 100));
        $taxId = $this->normalizeTaxId((string) ($consumer['document'] ?? ''));
        $phone = $this->normalizePhone((string) ($consumer['phone'] ?? ''));

        $body = [
            'correlationID' => $correlationID,
            'value' => (string) $valueCents,
            'comment' => 'Pedido #'.$externalId,
            'customer' => array_filter([
                'name' => $this->truncate((string) ($consumer['name'] ?? 'Cliente'), 80),
                'email' => filter_var(trim((string) ($consumer['email'] ?? '')), FILTER_VALIDATE_EMAIL)
                    ? trim((string) $consumer['email'])
                    : null,
                'taxID' => $taxId !== '' ? $taxId : null,
                'phone' => $phone !== '' ? $phone : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        $url = rtrim($this->baseUrl($credentials), '/').'/api/openpix/v1/charge';
        $response = Http::timeout(25)
            ->connectTimeout(10)
            ->withHeaders($this->authHeaders($credentials))
            ->post($url, $body);

        if (! $response->successful()) {
            $msg = $this->extractErrorMessage($response);
            throw new \RuntimeException('Woovi: '.$msg);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('Woovi: resposta inválida ao criar cobrança.');
        }

        $charge = $data['charge'] ?? $data;
        if (! is_array($charge)) {
            $charge = [];
        }

        $globalId = $charge['transactionID'] ?? $charge['transactionId'] ?? $charge['globalID'] ?? null;
        $transactionId = is_string($globalId) && $globalId !== ''
            ? $globalId
            : ($charge['correlationID'] ?? $correlationID);

        $brCode = $charge['brCode'] ?? $charge['pixCopiaECola'] ?? null;
        $qrImage = $charge['qrCodeImage'] ?? $charge['qrCodeBase64'] ?? null;
        if (($qrImage === null || $qrImage === '') && isset($charge['paymentMethods']) && is_array($charge['paymentMethods'])) {
            $pix = $charge['paymentMethods']['pix'] ?? null;
            if (is_array($pix)) {
                $qrImage = $pix['qrCodeImage'] ?? $pix['qrCodeBase64'] ?? $qrImage;
            }
        }

        return [
            'transaction_id' => (string) $transactionId,
            'qrcode' => is_string($qrImage) && $qrImage !== '' ? $qrImage : null,
            'copy_paste' => is_string($brCode) && $brCode !== '' ? $brCode : null,
            'raw' => $data,
        ];
    }

    public function getTransactionStatus(string $transactionId, array $credentials): ?string
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return null;
        }

        $url = rtrim($this->baseUrl($credentials), '/').'/api/openpix/v1/charge/'.rawurlencode($transactionId);
        try {
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->withHeaders($this->authHeaders($credentials))
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $charge = $data['charge'] ?? $data;
        if (! is_array($charge)) {
            return null;
        }

        return $this->mapChargeStatus($charge['status'] ?? null);
    }

    /**
     * Transferência PIX (cashout). POST /api/v1/transfer
     *
     * @return array{ok: bool, correlation_id?: string|null, error?: string, raw?: array}
     */
    public function createTransfer(array $credentials, int $valueCents, string $toPixKey): array
    {
        $appId = $this->appId($credentials);
        $fromPixKey = trim((string) ($credentials['from_pix_key'] ?? ''));
        if ($appId === '' || $fromPixKey === '') {
            return ['ok' => false, 'error' => 'Woovi: AppID ou chave PIX de origem (from_pix_key) não configurados.'];
        }

        $toPixKey = trim($toPixKey);
        if ($toPixKey === '' || $valueCents < 1) {
            return ['ok' => false, 'error' => 'Dados de transferência inválidos.'];
        }

        $url = rtrim($this->baseUrl($credentials), '/').'/api/v1/transfer';
        $response = Http::timeout(40)
            ->connectTimeout(10)
            ->withHeaders($this->authHeaders($credentials))
            ->post($url, [
                'value' => $valueCents,
                'fromPixKey' => $fromPixKey,
                'toPixKey' => $toPixKey,
            ]);

        $data = $response->json();
        if (! is_array($data)) {
            return ['ok' => false, 'error' => 'Woovi: resposta inválida na transferência.'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'Woovi: '.$this->extractErrorMessage($response)];
        }

        $tx = $data['transaction'] ?? $data;
        if (! is_array($tx)) {
            $tx = [];
        }

        $correlationId = $tx['correlationID'] ?? $tx['correlationId'] ?? null;
        $globalId = $tx['transactionID'] ?? $tx['transactionId'] ?? $tx['globalID'] ?? $tx['globalId'] ?? $tx['id'] ?? null;
        $transactionId = null;
        if (is_string($globalId) && trim($globalId) !== '') {
            $transactionId = trim($globalId);
        } elseif (is_int($globalId) || is_float($globalId)) {
            $transactionId = (string) $globalId;
        } elseif (is_string($correlationId) && trim($correlationId) !== '') {
            $transactionId = trim($correlationId);
        }

        return [
            'ok' => true,
            'transaction_id' => $transactionId,
            'correlation_id' => is_string($correlationId) ? trim($correlationId) : null,
            'raw' => $data,
        ];
    }

    /**
     * Consulta status de transferência (transação) quando o ID global estiver disponível.
     */
    public function getTransferStatus(string $transactionCorrelationId, array $credentials): ?string
    {
        $transactionCorrelationId = trim($transactionCorrelationId);
        if ($transactionCorrelationId === '') {
            return null;
        }

        $url = rtrim($this->baseUrl($credentials), '/').'/api/v1/transaction/'.rawurlencode($transactionCorrelationId);
        try {
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->withHeaders($this->authHeaders($credentials))
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        $tx = $data['transaction'] ?? $data;
        if (! is_array($tx)) {
            return null;
        }

        $status = $tx['status'] ?? $data['status'] ?? null;
        $st = is_string($status) ? strtoupper(trim($status)) : '';

        return match ($st) {
            'COMPLETED', 'CONFIRMED', 'PAID' => 'paid',
            'FAILED', 'CANCELLED', 'REJECTED' => 'cancelled',
            default => $st !== '' ? strtolower($st) : 'pending',
        };
    }

    public function createCardPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        array $card
    ): array {
        throw new \RuntimeException('Woovi não suporta pagamento com cartão nesta integração.');
    }

    public function createBoletoPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $notificationUrl
    ): array {
        throw new \RuntimeException('Woovi não suporta boleto nesta integração.');
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function appId(array $credentials): string
    {
        $v = $credentials['app_id'] ?? $credentials['authorization'] ?? '';

        return is_string($v) ? trim($v) : '';
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function authHeaders(array $credentials): array
    {
        $appId = $this->appId($credentials);
        $headers = [
            'Authorization' => $appId,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function baseUrl(array $credentials): string
    {
        if ($this->truthy($credentials['sandbox'] ?? false)) {
            return self::DEFAULT_SANDBOX_BASE;
        }

        return self::DEFAULT_BASE;
    }

    private function truthy(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    private function mapChargeStatus(mixed $status): ?string
    {
        if ($status === null) {
            return null;
        }
        $s = is_string($status) ? strtoupper(trim($status)) : strtoupper(trim((string) $status));

        return match ($s) {
            'COMPLETED', 'PAID' => 'paid',
            'ACTIVE', 'PENDING' => 'pending',
            'EXPIRED', 'CANCELLED' => 'cancelled',
            default => strtolower($s),
        };
    }

    private function normalizeTaxId(string $document): string
    {
        $digits = preg_replace('/\D/', '', $document) ?? '';

        return is_string($digits) ? $digits : '';
    }

    private function normalizePhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone) ?? '';

        return is_string($d) ? $d : '';
    }

    private function truncate(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max);
    }

    private function extractErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $errors = $json['errors'] ?? null;
            if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
                $m = $errors[0]['message'] ?? null;
                if (is_string($m) && $m !== '') {
                    return $m;
                }
            }
            $m = $json['message'] ?? $json['error'] ?? null;
            if (is_string($m) && $m !== '') {
                return $m;
            }
        }

        return 'Erro HTTP '.$response->status();
    }
}
