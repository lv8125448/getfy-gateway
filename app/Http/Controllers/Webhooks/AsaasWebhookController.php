<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    /**
     * Handle Asaas webhook (POST /webhooks/gateways/asaas).
     * Payload: event (PAYMENT_RECEIVED, PAYMENT_CONFIRMED, PAYMENT_OVERDUE, etc.), payment (object with id).
     * Always respond 200 when order not found to avoid retries.
     */
    public function handle(Request $request): JsonResponse
    {
        $payment = $request->input('payment');
        if (! is_array($payment)) {
            return response()->json(['received' => true]);
        }
        $transactionId = $payment['id'] ?? null;
        if ($transactionId === null || $transactionId === '') {
            return response()->json(['received' => true]);
        }
        $transactionId = (string) $transactionId;

        $order = Order::where('gateway', 'asaas')->where('gateway_id', $transactionId)->first();
        if (! $order) {
            Log::debug('AsaasWebhook: order not found', ['gateway_id' => $transactionId]);
            return response()->json(['received' => true]);
        }

        if (! $this->verifyWebhookSignature('asaas', $order->tenant_id, $request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $eventType = strtoupper((string) $request->input('event', ''));
        $event = 'order.pending';
        $mappedStatus = 'pending';

        if (in_array($eventType, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
            $event = 'order.paid';
            $mappedStatus = 'paid';
        } elseif (in_array($eventType, ['PAYMENT_CANCELLED', 'PAYMENT_REFUNDED'], true)) {
            $event = 'order.cancelled';
            $mappedStatus = 'cancelled';
        } elseif (in_array($eventType, ['PAYMENT_OVERDUE'], true)) {
            $event = 'order.pending';
            $mappedStatus = 'pending';
        }

        ProcessPaymentWebhook::dispatchSync('asaas', $transactionId, $event, $mappedStatus, $request->all());

        return response()->json(['received' => true]);
    }

    /**
     * Verifica assinatura do webhook quando webhook_secret estiver configurado.
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
