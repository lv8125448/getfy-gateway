<?php

namespace App\Services;

use App\Models\CheckoutSession;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

class UtmifyService
{
    private const ENDPOINT = 'https://api.utmify.com.br/api-credentials/orders';

    /**
     * Build payload and send order to UTMfy API.
     *
     * @param  array{approved_at?: string|null, refunded_at?: string|null}  $options
     */
    public function sendOrder(
        Order $order,
        string $utmifyStatus,
        string $apiKey,
        array $options = []
    ): void {
        $body = $this->buildPayload($order, $utmifyStatus, $options);
        $this->post($apiKey, $body);
    }

    /**
     * @param  array{approved_at?: string|null, refunded_at?: string|null, is_test?: bool}  $options
     * @return array<string, mixed>
     */
    public function buildPayload(Order $order, string $utmifyStatus, array $options = []): array
    {
        $order->loadMissing(['user', 'orderItems.product', 'orderItems.productOffer', 'orderItems.subscriptionPlan']);

        $session = CheckoutSession::where('order_id', $order->id)->first();

        $orderId = $order->gateway_id ?: (string) $order->id;
        $paymentMethod = $this->mapPaymentMethod($order->gateway);
        $createdAt = $order->created_at->utc()->format('Y-m-d H:i:s');
        $approvedDate = $options['approved_at'] ?? ($utmifyStatus === 'paid' ? $order->updated_at->utc()->format('Y-m-d H:i:s') : null);
        $refundedAt = $options['refunded_at'] ?? null;

        $customerName = $session?->name ?? $order->user?->name ?? '';
        $customer = [
            'name' => $customerName,
            'email' => $order->email ?? '',
            'phone' => $order->phone ?? '',
            'document' => $order->cpf ?? '',
            'country' => 'BR',
            'ip' => $order->customer_ip ?? '',
        ];

        $products = [];
        foreach ($order->orderItems as $item) {
            $product = $item->product;
            $planId = $item->product_offer_id ?? $item->subscription_plan_id;
            $planName = null;
            if ($item->productOffer) {
                $planName = $item->productOffer->name;
            } elseif ($item->subscriptionPlan) {
                $planName = $item->subscriptionPlan->name;
            }
            $products[] = [
                'id' => (string) ($product?->id ?? $item->product_id ?? $item->id),
                'name' => $product?->name ?? 'Produto',
                'planId' => $planId ? (string) $planId : null,
                'planName' => $planName,
                'quantity' => 1,
                'priceInCents' => (int) round((float) $item->amount * 100),
            ];
        }

        if (empty($products)) {
            $mainProduct = $order->product;
            $products[] = [
                'id' => (string) ($mainProduct?->id ?? $order->product_id),
                'name' => $mainProduct?->name ?? 'Produto',
                'planId' => null,
                'planName' => null,
                'quantity' => 1,
                'priceInCents' => (int) round((float) $order->amount * 100),
            ];
        }

        $trackingParameters = [
            'src' => null,
            'sck' => null,
            'utm_source' => $session?->utm_source,
            'utm_campaign' => $session?->utm_campaign,
            'utm_medium' => $session?->utm_medium,
            'utm_content' => null,
            'utm_term' => null,
        ];

        $totalCents = (int) round((float) $order->amount * 100);
        $commission = [
            'totalPriceInCents' => $totalCents,
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => $totalCents,
        ];

        $body = [
            'orderId' => $orderId,
            'platform' => 'Primicia',
            'paymentMethod' => $paymentMethod,
            'status' => $utmifyStatus,
            'createdAt' => $createdAt,
            'approvedDate' => $approvedDate,
            'refundedAt' => $refundedAt,
            'customer' => $customer,
            'products' => $products,
            'trackingParameters' => $trackingParameters,
            'commission' => $commission,
        ];

        if (! empty($options['is_test'])) {
            $body['isTest'] = true;
        }

        return $body;
    }

    /**
     * POST to UTMfy API. Throws on failure.
     */
    public function post(string $apiKey, array $body): \Illuminate\Http\Client\Response
    {
        $response = Http::timeout(15)
            ->withHeaders(['x-api-token' => $apiKey])
            ->post(self::ENDPOINT, $body);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'UTMfy API error: ' . $response->status() . ' ' . $response->body()
            );
        }

        return $response;
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
            return 'boleto';
        }
        if (str_contains($g, 'card') || str_contains($g, 'credit') || str_contains($g, 'cartao')) {
            return 'credit_card';
        }

        return 'pix';
    }
}
