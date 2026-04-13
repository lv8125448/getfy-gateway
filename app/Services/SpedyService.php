<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class SpedyService
{
    private const BASE_URL_PRODUCTION = 'https://api.spedy.com.br/v1';

    private const BASE_URL_SANDBOX = 'https://sandbox-api.spedy.com.br/v1';

    /**
     * Create order in Spedy and trigger invoice issuance.
     *
     * @throws \RuntimeException
     */
    public function createOrderAndIssueInvoices(Order $order, string $apiKey, string $environment): void
    {
        $baseUrl = $environment === \App\Models\SpedyIntegration::ENVIRONMENT_SANDBOX
            ? self::BASE_URL_SANDBOX
            : self::BASE_URL_PRODUCTION;

        $order->loadMissing([
            'user',
            'orderItems.product',
            'orderItems.productOffer',
            'orderItems.subscriptionPlan',
            'product',
        ]);

        $payload = $this->buildOrderPayload($order);
        $response = $this->post($baseUrl, $apiKey, 'orders', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Spedy API error on create order: ' . $response->status() . ' ' . $response->body()
            );
        }

        $data = $response->json();
        $spedyOrderId = $data['id'] ?? null;

        if ($spedyOrderId) {
            $issueResponse = $this->post($baseUrl, $apiKey, "orders/{$spedyOrderId}/invoices/issue", []);
            if (! $issueResponse->successful()) {
                throw new \RuntimeException(
                    'Spedy API error on issue invoices: ' . $issueResponse->status() . ' ' . $issueResponse->body()
                );
            }
        }
    }

    /**
     * Build OrderPostDto payload from Order.
     *
     * @return array<string, mixed>
     */
    public function buildOrderPayload(Order $order): array
    {
        $session = CheckoutSession::where('order_id', $order->id)->first();
        $customerName = $session?->name ?? $order->user?->name ?? trim($order->email ?: 'Cliente');
        if ($customerName === '') {
            $customerName = 'Cliente';
        }

        $customer = [
            'name' => mb_substr($customerName, 0, 80),
            'federalTaxNumber' => $this->normalizeCpfCnpj($order->cpf),
            'email' => $order->email ? mb_substr($order->email, 0, 50) : null,
            'phone' => $order->phone ? mb_substr(preg_replace('/\D/', '', $order->phone), 0, 15) : null,
        ];

        $items = [];
        foreach ($order->orderItems as $item) {
            $product = $item->product;
            $productName = $product?->name ?? 'Produto';
            $amount = (float) $item->amount;
            $code = (string) ($item->product_offer_id ?? $item->subscription_plan_id ?? $item->product_id ?? $item->id);
            $items[] = [
                'quantity' => 1,
                'price' => $amount,
                'amount' => $amount,
                'product' => [
                    'code' => $code,
                    'name' => $productName,
                    'price' => $amount,
                ],
            ];
        }

        if (empty($items)) {
            $mainProduct = $order->product;
            $amount = (float) $order->amount;
            $items[] = [
                'quantity' => 1,
                'price' => $amount,
                'amount' => $amount,
                'product' => [
                    'code' => (string) ($mainProduct?->id ?? $order->product_id ?? $order->id),
                    'name' => $mainProduct?->name ?? 'Produto',
                    'price' => $amount,
                ],
            ];
        }

        $amount = (float) $order->amount;
        $date = $order->created_at->utc()->format('Y-m-d\TH:i:s\Z');

        $payload = [
            'transactionId' => (string) $order->id,
            'customer' => $customer,
            'amount' => $amount,
            'date' => $date,
            'status' => 'approved',
            'paymentMethod' => $this->mapPaymentMethod($order->gateway),
            'items' => $items,
        ];

        return $payload;
    }

    private function normalizeCpfCnpj(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    private function mapPaymentMethod(?string $gateway): string
    {
        if (! $gateway) {
            return 'pix';
        }
        $g = strtolower($gateway);
        if (str_contains($g, 'pix')) {
            return 'pix';
        }
        if (str_contains($g, 'boleto') || str_contains($g, 'ticket')) {
            return 'billetBank';
        }
        if (str_contains($g, 'card') || str_contains($g, 'credit') || str_contains($g, 'cartao')) {
            return 'creditCard';
        }
        if (str_contains($g, 'debit')) {
            return 'debitCard';
        }

        return 'pix';
    }

    private function post(string $baseUrl, string $apiKey, string $path, array $body): Response
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        return Http::timeout(30)
            ->withHeaders(['X-Api-Key' => $apiKey])
            ->post($url, $body);
    }
}
