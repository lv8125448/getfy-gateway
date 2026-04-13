<?php

namespace App\Http\Controllers\Webhooks;

use App\Gateways\GatewayRegistry;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    /**
     * Handle Mercado Pago webhook (POST /webhooks/gateways/mercadopago).
     * Payload: type, action (e.g. payment.created, payment.updated), data.id (payment id).
     * Fetches real payment status from MP API and dispatches job with correct event/status.
     * Always respond 200 when order not found to avoid MP retries.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $data = $request->input('data', []);
        $dataId = is_array($data) ? ($data['id'] ?? null) : null;
        if ($dataId === null) {
            return response()->json(['received' => true]);
        }

        $transactionId = (string) $dataId;

        $order = Order::where('gateway', 'mercadopago')->where('gateway_id', $transactionId)->first();
        if (! $order) {
            Log::debug('MercadoPagoWebhook: order not found', ['gateway_id' => $transactionId]);
            return response()->json(['received' => true]);
        }

        $credential = GatewayCredential::resolveForPayment($order->tenant_id, 'mercadopago');

        $event = 'order.pending';
        $status = 'pending';

        if ($credential) {
            $credentials = $credential->getDecryptedCredentials();
            $driver = GatewayRegistry::driver('mercadopago');
            if (! empty($credentials) && $driver) {
                $apiStatus = $driver->getTransactionStatus($transactionId, $credentials);
                if ($apiStatus === 'paid') {
                    $event = 'order.paid';
                    $status = 'paid';
                } elseif ($apiStatus === 'cancelled') {
                    $event = 'order.cancelled';
                    $status = 'cancelled';
                }
            }
        }

        ProcessPaymentWebhook::dispatchSync('mercadopago', $transactionId, $event, $status, $payload);

        return response()->json(['received' => true]);
    }
}
