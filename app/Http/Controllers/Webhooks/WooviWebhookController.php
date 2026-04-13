<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooviWebhookController extends Controller
{
    /**
     * Webhook Woovi/OpenPix: cobrança paga (charge COMPLETED/PAID).
     * O `gateway_id` do pedido deve coincidir com transactionID/globalID da charge (ou correlationID como fallback).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $charge = $this->extractCharge($payload);
        if ($charge === null || ! is_array($charge)) {
            return response()->json(['message' => 'charge not found in payload'], 400);
        }

        $transactionId = $this->extractChargeTransactionId($charge);
        if ($transactionId === null || $transactionId === '') {
            return response()->json(['message' => 'transaction id required'], 400);
        }

        $statusRaw = $charge['status'] ?? $payload['status'] ?? null;
        $status = is_string($statusRaw) ? strtoupper(trim($statusRaw)) : '';

        $event = $payload['event'] ?? $payload['type'] ?? '';
        $eventStr = is_string($event) ? strtoupper($event) : '';

        $isPaid = in_array($status, ['COMPLETED', 'PAID'], true)
            || str_contains($eventStr, 'CHARGE_COMPLETED');

        if (! $isPaid) {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        $correlationId = $charge['correlationID'] ?? $charge['correlationId'] ?? null;
        $correlationStr = is_string($correlationId) ? trim($correlationId) : '';

        $order = Order::where('gateway', 'woovi')
            ->where(function ($q) use ($transactionId, $correlationStr) {
                $q->where('gateway_id', $transactionId);
                if ($correlationStr !== '') {
                    $q->orWhere('gateway_id', $correlationStr);
                }
            })
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $txForJob = (string) $order->gateway_id;

        ProcessPaymentWebhook::dispatchSync('woovi', $txForJob, 'order.paid', 'paid', $payload);

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extractCharge(array $payload): ?array
    {
        foreach (['charge', 'pix.charge', 'data.charge', 'data.pix.charge'] as $path) {
            $c = data_get($payload, $path);
            if (is_array($c) && $c !== []) {
                return $c;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function extractChargeTransactionId(array $charge): ?string
    {
        foreach (['transactionID', 'transactionId', 'globalID', 'globalId', 'id', 'correlationID', 'correlationId'] as $key) {
            $v = $charge[$key] ?? null;
            if (is_string($v)) {
                $t = trim($v);
                if ($t !== '') {
                    return $t;
                }
            }
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }
        }

        return null;
    }
}
