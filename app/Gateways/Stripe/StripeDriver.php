<?php

namespace App\Gateways\Stripe;

use App\Gateways\Contracts\GatewayDriver;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe driver – cartão de crédito (global).
 * Ver: https://docs.stripe.com/api, https://stripe.com/docs/currencies
 * Parcelamento não utilizado neste gateway; pagamento sempre à vista (1x).
 */
class StripeDriver implements GatewayDriver
{
    /** Zero-decimal currencies (amount = integer units, no * 100). */
    private const ZERO_DECIMAL_CURRENCIES = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf',
        'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    ];

    public function testConnection(array $credentials): bool
    {
        $secret = trim($credentials['secret_key'] ?? '');
        if ($secret === '') {
            return false;
        }
        try {
            $stripe = new StripeClient($secret);
            $stripe->balance->retrieve();
            return true;
        } catch (\Throwable $e) {
            Log::debug('StripeDriver testConnection failed', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function createPixPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $postbackUrl
    ): array {
        throw new \RuntimeException('Stripe não suporta PIX neste gateway. Use cartão de crédito.');
    }

    public function createBoletoPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        string $notificationUrl
    ): array {
        throw new \RuntimeException('Stripe não suporta boleto neste gateway. Use cartão de crédito.');
    }

    /**
     * Cria e confirma PaymentIntent com cartão (payment_method = Stripe PaymentMethod id).
     *
     * @param  array<string, string>  $credentials
     * @param  array{name: string, document: string, email: string}  $consumer
     * @param  array{payment_token: string, card_mask?: string, currency?: string}  $card
     * @return array{transaction_id: string, status?: string, client_secret?: string}
     */
    public function createCardPayment(
        array $credentials,
        float $amount,
        array $consumer,
        string $externalId,
        array $card
    ): array {
        $secret = trim($credentials['secret_key'] ?? '');
        if ($secret === '') {
            throw new \RuntimeException('Stripe: chave secreta não configurada.');
        }

        $paymentMethodId = trim($card['payment_token'] ?? '');
        if ($paymentMethodId === '' || ! str_starts_with($paymentMethodId, 'pm_')) {
            throw new \RuntimeException('Stripe: método de pagamento inválido. Preencha os dados do cartão novamente.');
        }

        $currency = strtolower(trim($card['currency'] ?? 'usd'));
        if (strlen($currency) !== 3) {
            $currency = 'usd';
        }

        $amountInSmallestUnit = $this->amountToSmallestUnit($amount, $currency);

        if ($amountInSmallestUnit < 50 && $currency === 'usd') {
            throw new \RuntimeException('Stripe: valor mínimo é 0.50 USD ou equivalente.');
        }

        $returnUrl = trim($card['return_url'] ?? '');
        if ($returnUrl === '') {
            $returnUrl = null;
        }

        $stripe = new StripeClient($secret);
        $params = [
            'amount' => $amountInSmallestUnit,
            'currency' => $currency,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'metadata' => ['order_id' => $externalId],
            'receipt_email' => $consumer['email'] ?? null,
        ];
        if ($returnUrl !== null) {
            $params['return_url'] = $returnUrl;
        }

        try {
            $paymentIntent = $stripe->paymentIntents->create($params);

            $status = $paymentIntent->status ?? null;
            $transactionId = $paymentIntent->id ?? '';

            if ($status === 'succeeded') {
                return [
                    'transaction_id' => $transactionId,
                    'status' => 'paid',
                ];
            }

            if ($status === 'requires_action' && ! empty($paymentIntent->client_secret)) {
                return [
                    'transaction_id' => $transactionId,
                    'status' => 'requires_action',
                    'client_secret' => $paymentIntent->client_secret,
                ];
            }

            if ($status === 'requires_payment_method') {
                throw new \RuntimeException('Pagamento recusado. Verifique os dados do cartão ou tente outro cartão.');
            }

            return [
                'transaction_id' => $transactionId,
                'status' => $status ?? 'pending',
            ];
        } catch (ApiErrorException $e) {
            $code = $e->getStripeCode() ?? '';
            $message = $e->getMessage();
            Log::warning('StripeDriver createCardPayment failed', [
                'order_id' => $externalId,
                'code' => $code,
                'message' => $message,
            ]);
            if (str_contains(strtolower($message), 'card_declined') || $code === 'card_declined') {
                throw new \RuntimeException('Cartão recusado. Verifique os dados ou tente outro cartão.');
            }
            if (str_contains(strtolower($message), 'incorrect_cvc') || $code === 'incorrect_cvc') {
                throw new \RuntimeException('Código de segurança (CVV) incorreto.');
            }
            if (str_contains(strtolower($message), 'expired_card') || $code === 'expired_card') {
                throw new \RuntimeException('Cartão expirado.');
            }
            throw new \RuntimeException('Não foi possível processar o pagamento. Tente novamente.');
        } catch (\Throwable $e) {
            Log::warning('StripeDriver createCardPayment error', [
                'order_id' => $externalId,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Não foi possível processar o pagamento. Tente novamente.');
        }
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public function getTransactionStatus(string $transactionId, array $credentials): ?string
    {
        $secret = trim($credentials['secret_key'] ?? '');
        if ($secret === '') {
            return null;
        }
        try {
            $stripe = new StripeClient($secret);
            $pi = $stripe->paymentIntents->retrieve($transactionId);
            $status = $pi->status ?? null;
            if ($status === 'succeeded') {
                return 'paid';
            }
            if (in_array($status, ['requires_action', 'requires_confirmation', 'requires_payment_method', 'processing'], true)) {
                return 'pending';
            }
            if ($status === 'canceled') {
                return 'cancelled';
            }
            return 'pending';
        } catch (\Throwable $e) {
            Log::debug('StripeDriver getTransactionStatus failed', ['tx' => $transactionId, 'message' => $e->getMessage()]);
            return null;
        }
    }

    private function amountToSmallestUnit(float $amount, string $currency): int
    {
        $currency = strtolower($currency);
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }
        return (int) round($amount * 100);
    }
}
