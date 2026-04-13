<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para PIX recorrente (assinatura) da Pushin Pay.
 * Endpoint: POST /pix/cashIn/subscription.
 */
class PushinPayPixRecorrenteService
{
    private const BASE_URL_PRODUCTION = 'https://api.pushinpay.com.br/api';

    private const BASE_URL_SANDBOX = 'https://api-sandbox.pushinpay.com.br/api';

    /** @var array<string, string> */
    private array $credentials;

    private string $baseUrl;

    /**
     * @param  array<string, string>  $credentials  api_token, sandbox
     */
    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
        $sandbox = isset($credentials['sandbox']) && filter_var($credentials['sandbox'], FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = $sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PRODUCTION;
    }

    /**
     * Mapeia intervalo do plano para frequency da API.
     * Pushin Pay aceita apenas 2, 3 ou 4 (ex.: 2 = mensal, 3 = trimestral, 4 = anual).
     *
     * @return int
     */
    public static function intervalToFrequency(string $interval): int
    {
        return match ($interval) {
            SubscriptionPlan::INTERVAL_WEEKLY => 2,
            SubscriptionPlan::INTERVAL_MONTHLY => 2,
            SubscriptionPlan::INTERVAL_QUARTERLY => 3,
            SubscriptionPlan::INTERVAL_SEMI_ANNUAL => 4,
            SubscriptionPlan::INTERVAL_ANNUAL => 4,
            SubscriptionPlan::INTERVAL_LIFETIME => 4,
            default => 2,
        };
    }

    /**
     * Cria assinatura PIX recorrente. Retorna dados da primeira cobrança (QR) e subscription_id.
     *
     * @param  array{name: string, document: string, email: string}  $consumer
     * @return array{transaction_id: string, qrcode?: string, copy_paste?: string, subscription_id: string}
     */
    public function createSubscription(
        float $amount,
        array $consumer,
        string $webhookUrl,
        int $frequency,
        string $name,
        string $comment = ''
    ): array {
        $token = trim($this->credentials['api_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Pushin Pay: API Token não configurado.');
        }
        $valueCentavos = (int) round($amount * 100);
        if ($valueCentavos < 50) {
            $valueCentavos = 50;
        }
        $email = trim($consumer['email'] ?? '');
        if ($email === '' || ! str_contains($email, '@')) {
            throw new \RuntimeException('Pushin Pay: e-mail do comprador é obrigatório para PIX automático.');
        }
        $document = preg_replace('/\D/', '', $consumer['document'] ?? '');
        if (strlen($document) < 11) {
            throw new \RuntimeException('Pushin Pay: CPF do comprador é obrigatório para PIX automático.');
        }
        $customer = [
            'name' => mb_substr(trim($consumer['name'] ?? '') ?: 'Cliente', 0, 100),
            'email' => $email,
            'phoneNumber' => '+5511999999999',
            'document' => [
                'type' => strlen($document) === 11 ? 'CPF' : 'CNPJ',
                'number' => $document,
            ],
        ];
        $commentText = $comment ?: $name;
        $commentText = mb_substr((string) $commentText, 0, 30);
        $nameText = mb_substr((string) $name, 0, 50);

        $body = [
            'value' => $valueCentavos,
            'frequency' => $frequency,
            'name' => $nameText,
            'comment' => $commentText,
            'pix_recurring_retry_policy' => 2,
            'customer' => $customer,
            'webhook_url' => $webhookUrl,
        ];
        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($this->baseUrl . '/pix/cashIn/subscription', $body);

        if (! $response->successful()) {
            $errBody = $response->json();
            if (! is_array($errBody)) {
                $raw = $response->body();
                $errBody = [];
                if ($raw !== '' && $raw !== null) {
                    $decoded = json_decode($raw, true);
                    $errBody = is_array($decoded) ? $decoded : ['raw' => mb_substr((string) $raw, 0, 500)];
                }
            }
            $message = $errBody['message'] ?? $errBody['error'] ?? $errBody['msg'] ?? null;
            if (is_array($message)) {
                $message = implode(' ', array_filter($message));
            }
            $message = $message !== null ? trim((string) $message) : '';
            $errors = $errBody['errors'] ?? [];
            if (is_array($errors) && $errors !== []) {
                $parts = [];
                foreach ($errors as $field => $list) {
                    if (is_array($list)) {
                        $parts[] = implode(' ', $list);
                    } else {
                        $parts[] = (string) $list;
                    }
                }
                $message = $message ? $message . ' ' . implode(' ', $parts) : implode(' ', $parts);
            }
            $statusCode = $response->status();
            Log::warning('PushinPayPixRecorrenteService createSubscription failed', [
                'status' => $statusCode,
                'response_body' => $errBody,
            ]);
            $userMessage = $message ?: 'Não foi possível criar a assinatura PIX. Verifique os dados e tente novamente.';
            throw new \RuntimeException('Pushin Pay: (' . $statusCode . ') ' . $userMessage);
        }

        $data = $response->json();
        if (! is_array($data)) {
            Log::warning('PushinPayPixRecorrenteService createSubscription invalid response', [
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 500),
            ]);
            throw new \RuntimeException('Pushin Pay: resposta inválida da API. Tente novamente.');
        }
        if (isset($data['success']) && $data['success'] === false) {
            $msg = $data['message'] ?? $data['error'] ?? 'Não foi possível criar a assinatura PIX.';
            throw new \RuntimeException('Pushin Pay: ' . (is_string($msg) ? $msg : json_encode($msg)));
        }
        $id = $data['id'] ?? null;
        $subscriptionId = $data['subscription_id'] ?? null;
        if (empty($id)) {
            throw new \RuntimeException('Pushin Pay: resposta sem identificador da transação.');
        }

        return [
            'transaction_id' => (string) $id,
            'qrcode' => $data['qr_code_base64'] ?? null,
            'copy_paste' => $data['qr_code'] ?? null,
            'subscription_id' => $subscriptionId !== null ? (string) $subscriptionId : '',
        ];
    }
}
