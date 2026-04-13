<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook (POST /webhooks/gateways/stripe).
     * Verifies signature using the tenant's webhook secret (from credential).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (! is_string($payload) || $payload === '' || ! is_string($sigHeader) || $sigHeader === '') {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        $payloadData = json_decode($payload, true);
        if (! is_array($payloadData)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $eventType = $payloadData['type'] ?? null;
        $paymentIntentId = null;
        if ($eventType === 'payment_intent.succeeded' && isset($payloadData['data']['object']['id'])) {
            $paymentIntentId = $payloadData['data']['object']['id'];
        }

        if ($paymentIntentId === null) {
            return response()->json(['received' => true]);
        }

        $order = Order::where('gateway', 'stripe')->where('gateway_id', $paymentIntentId)->first();
        if (! $order) {
            Log::warning('StripeWebhook: order not found', ['gateway_id' => $paymentIntentId]);
            return response()->json(['received' => true]);
        }

        $credential = GatewayCredential::resolveForPayment($order->tenant_id, 'stripe');

        if (! $credential) {
            return response()->json(['message' => 'Credential not found'], 400);
        }

        $credentials = $credential->getDecryptedCredentials();
        $webhookSecret = trim($credentials['webhook_secret'] ?? '');
        if ($webhookSecret === '') {
            Log::warning('StripeWebhook: webhook_secret not configured', ['tenant_id' => $order->tenant_id]);
            return response()->json(['message' => 'Webhook not configured'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('StripeWebhook: invalid payload', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('StripeWebhook: signature verification failed', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $piId = $paymentIntent->id ?? null;
            if ($piId) {
                ProcessPaymentWebhook::dispatchSync('stripe', $piId, 'payment_intent.succeeded', 'paid', $payloadData);
            }
        }

        return response()->json(['received' => true]);
    }
}
