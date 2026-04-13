<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use App\Models\Withdrawal;
use App\Services\MerchantWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SpacepagWebhookController extends Controller
{
    /** @var list<string> */
    private const WITHDRAWAL_EVENTS = ['payment.created', 'payment.paid', 'payment.cancelled'];

    public function handle(Request $request): JsonResponse
    {
        $event = $this->webhookPayload($request, 'event');
        $rawTx = $this->webhookPayload($request, 'transaction_id');
        $status = $this->webhookPayload($request, 'status');

        $transactionId = self::normalizeWebhookScalar($rawTx);
        if ($transactionId === null) {
            return response()->json(['message' => 'transaction_id required'], 400);
        }

        $event = trim(is_string($event) ? $event : (is_scalar($event) ? (string) $event : ''));
        $eventLower = strtolower($event);
        $eventNormalized = in_array($eventLower, self::WITHDRAWAL_EVENTS, true) ? $eventLower : $event;

        // 1) Saque PIX (pixout) antes de cob: evita colisão de transaction_id e garante markPaid.
        $withdrawal = $this->findSpacepagWithdrawal($transactionId, $request);
        if ($withdrawal !== null) {
            $tenantId = (int) $withdrawal->tenant_id;
            if (! $this->verifyWebhookSignature('spacepag', $tenantId, $request)) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if ($this->isSpacepagWithdrawalPaidEvent($eventNormalized, $eventLower, $request)) {
                if ($withdrawal->status === 'pending') {
                    MerchantWithdrawalService::markPaid($withdrawal->fresh());
                }
            } elseif ($this->isSpacepagWithdrawalCancelledEvent($eventNormalized, $request)) {
                if ($withdrawal->status === 'pending') {
                    $meta = is_array($withdrawal->payout_meta) ? $withdrawal->payout_meta : [];
                    $withdrawal->update([
                        'payout_meta' => $meta + [
                            'webhook_cancelled' => true,
                            'cancelled_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }

            return response()->json(['received' => true]);
        }

        $order = Order::where('gateway', 'spacepag')->where('gateway_id', $transactionId)->first();
        if ($order) {
            if (! $this->verifyWebhookSignature('spacepag', $order->tenant_id, $request)) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            ProcessPaymentWebhook::dispatchSync('spacepag', $transactionId, (string) $event, (string) $status, $request->all());

            return response()->json(['received' => true]);
        }

        if ($this->looksLikeSpacepagPixOutWebhook($eventNormalized, $eventLower, $request)) {
            Log::warning('Spacepag webhook: withdrawal not found for PIX payout notification', [
                'transaction_id' => $transactionId,
                'event' => $event,
                'external_id' => $this->webhookPayload($request, 'external_id'),
            ]);

            return response()->json(['message' => 'Withdrawal not found'], 404);
        }

        return response()->json(['message' => 'Order not found'], 404);
    }

    private function looksLikeSpacepagPixOutWebhook(string $eventNormalized, string $eventLower, Request $request): bool
    {
        if (in_array($eventNormalized, self::WITHDRAWAL_EVENTS, true)) {
            return true;
        }

        if ($this->hasPixOutPaymentAndReceiver($request)) {
            return true;
        }

        return $eventLower === 'order.paid' && $this->hasPixOutPaymentAndReceiver($request);
    }

    private function hasPixOutPaymentAndReceiver(Request $request): bool
    {
        foreach (['', 'data.'] as $prefix) {
            if ($request->filled($prefix.'payment') && $request->filled($prefix.'receiver')) {
                return true;
            }
        }

        return false;
    }

    private function isSpacepagWithdrawalPaidEvent(string $eventNormalized, string $eventLower, Request $request): bool
    {
        if ($eventNormalized === 'payment.paid') {
            return true;
        }

        if ($eventLower === 'order.paid' && $this->hasPixOutPaymentAndReceiver($request)) {
            return true;
        }

        $st = strtolower(trim((string) ($this->webhookPayload($request, 'status') ?? '')));

        return $st === 'paid' && $this->hasPixOutPaymentAndReceiver($request);
    }

    private function isSpacepagWithdrawalCancelledEvent(string $eventNormalized, Request $request): bool
    {
        if ($eventNormalized === 'payment.cancelled') {
            return true;
        }

        $st = strtolower(trim((string) ($this->webhookPayload($request, 'status') ?? '')));

        return $st === 'cancelled' && $this->hasPixOutPaymentAndReceiver($request);
    }

    private function findSpacepagWithdrawal(string $transactionId, Request $request): ?Withdrawal
    {
        $w = Withdrawal::query()
            ->where('payout_provider', 'spacepag')
            ->where('payout_external_id', $transactionId)
            ->first();

        if ($w !== null) {
            return $w;
        }

        $externalRaw = $this->webhookPayload($request, 'external_id');
        $externalId = is_string($externalRaw) ? trim($externalRaw) : (is_scalar($externalRaw) ? trim((string) $externalRaw) : '');
        if ($externalId !== '' && preg_match('/^getfy-withdrawal-(\d+)$/', $externalId, $m)) {
            return Withdrawal::query()
                ->where('payout_provider', 'spacepag')
                ->where('id', (int) $m[1])
                ->first();
        }

        return null;
    }

    /** Campo no root ou em `data` (alguns gateways encapsulam o payload). */
    private function webhookPayload(Request $request, string $key): mixed
    {
        foreach ([$key, 'data.'.$key] as $path) {
            if (! $request->has($path)) {
                continue;
            }
            $v = $request->input($path);
            if ($v === null) {
                continue;
            }
            if (is_string($v) && trim($v) === '') {
                continue;
            }

            return $v;
        }

        return null;
    }

    /**
     * transaction_id na API pode vir como número no JSON; sem isso o webhook respondia 400 e a Spacepag marcava postback falho.
     */
    private static function normalizeWebhookScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $v = trim($value);

            return $v === '' ? null : $v;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Verifica HMAC quando o header está presente. Sem header, aceita (a Spacepag não documenta assinatura em todos os ambientes).
     */
    private function verifyWebhookSignature(string $gatewaySlug, ?int $tenantId, Request $request): bool
    {
        $credential = GatewayCredential::resolveForPayment($tenantId, $gatewaySlug)
            ?? GatewayCredential::resolveForPayment(null, $gatewaySlug);
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
            return true;
        }
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
