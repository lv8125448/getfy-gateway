<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushinPayWebhookController extends Controller
{
    /**
     * Handle Pushin Pay webhook (POST /webhooks/gateways/pushinpay).
     * Payload: id, value, status ("created" | "paid" | "canceled"), end_to_end_id.
     * Always respond 200 when order not found to avoid Pushin Pay retries.
     */
    public function handle(Request $request): JsonResponse
    {
        $transactionId = $request->input('id');
        if ($transactionId === null || $transactionId === '') {
            return response()->json(['received' => true]);
        }
        $transactionId = (string) $transactionId;

        $order = Order::where('gateway', 'pushinpay')->where('gateway_id', $transactionId)->first();
        if (! $order) {
            Log::debug('PushinPayWebhook: order not found', ['gateway_id' => $transactionId]);
            return response()->json(['received' => true]);
        }

        if (! $this->verifyWebhookSignature('pushinpay', $order->tenant_id, $request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $status = strtolower((string) $request->input('status', ''));
        $event = 'order.pending';
        $mappedStatus = 'pending';

        if ($status === 'paid') {
            $event = 'order.paid';
            $mappedStatus = 'paid';
        } elseif ($status === 'canceled' || $status === 'cancelled') {
            $event = 'order.cancelled';
            $mappedStatus = 'cancelled';
        }

        ProcessPaymentWebhook::dispatchSync('pushinpay', $transactionId, $event, $mappedStatus, $request->all());

        return response()->json(['received' => true]);
    }

    /**
     * Verifica assinatura HMAC do body (header customizado). Se webhook_secret estiver configurado, exige match.
     */
    private function verifyWebhookSignature(string $gatewaySlug, ?int $tenantId, Request $request): bool
    {
        $credential = GatewayCredential::resolveForPayment($tenantId, $gatewaySlug);
        if (! $credential) {
            return true;
        }
        $credentials = $credential->getDecryptedCredentials();
        $secret = $credentials['webhook_secret'] ?? null;
        if ($secret === null || $secret === '') {
            return true;
        }
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $signature);
    }
}
