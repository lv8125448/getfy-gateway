<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\GatewayCredential;
use App\Models\Order;
use Efi\EfiPay;
use Efi\Exception\EfiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EfiWebhookController extends Controller
{
    /**
     * Handle Efí PIX webhook (POST .../webhooks/gateways/efi/pix).
     * BCB/Efí sends payload with pix array or txid; we find the order and dispatch job.
     */
    public function pix(Request $request): JsonResponse
    {
        $txid = $this->extractTxId($request);
        if ($txid === null || $txid === '') {
            return response()->json(['message' => 'txid required'], 400);
        }

        $order = Order::where('gateway', 'efi')->where('gateway_id', $txid)->first();
        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (! $this->verifyEfiWebhookSignature($order->tenant_id, $request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $event = 'order.paid';
        $status = 'paid';

        ProcessPaymentWebhook::dispatchSync('efi', $txid, $event, $status, $request->all());

        return response()->json(['received' => true]);
    }

    private function extractTxId(Request $request): ?string
    {
        $txid = $request->input('txid');
        if (is_string($txid) && $txid !== '') {
            return $txid;
        }

        $pix = $request->input('pix');
        if (is_array($pix) && isset($pix[0]['txid'])) {
            return $pix[0]['txid'];
        }
        if (is_array($pix) && isset($pix[0])) {
            $first = $pix[0];
            if (is_array($first) && isset($first['txid'])) {
                return $first['txid'];
            }
        }

        return null;
    }

    /**
     * Handle Efí charge notification (POST .../webhooks/gateways/efi/notification).
     * Efí sends POST with 'notification' = token; we call getNotification(token) to get charge_id and status.
     */
    public function notification(Request $request): JsonResponse
    {
        $token = $request->input('notification');
        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'notification token required'], 400);
        }

        $credentials = GatewayCredential::where('gateway_slug', 'efi')
            ->where('is_connected', true)
            ->get();

        foreach ($credentials as $credential) {
            $decrypted = $credential->getDecryptedCredentials();
            if (empty($decrypted)) {
                continue;
            }
            $options = $this->buildEfiOptions($decrypted);
            if (empty($options['certificate'])) {
                continue;
            }
            try {
                $api = EfiPay::getInstance($options);
                $response = $api->getNotification(['token' => $token]);
                break;
            } catch (EfiException $e) {
                Log::debug('EfiWebhookController getNotification failed', ['code' => $e->code]);
                continue;
            } catch (\Throwable $e) {
                Log::debug('EfiWebhookController getNotification error', ['message' => $e->getMessage()]);
                continue;
            }
        }

        if (! isset($response) || ! is_array($response)) {
            return response()->json(['received' => true]);
        }

        $data = $response['data'] ?? [];
        if (! is_array($data) || empty($data)) {
            return response()->json(['received' => true]);
        }

        $last = end($data);
        $chargeId = $last['identifiers']['charge_id'] ?? null;
        $statusCurrent = $last['status']['current'] ?? null;

        if ($chargeId !== null && is_string($statusCurrent) && strtolower($statusCurrent) === 'paid') {
            $order = Order::where('gateway', 'efi')->where('gateway_id', (string) $chargeId)->first();
            if ($order) {
                ProcessPaymentWebhook::dispatchSync('efi', (string) $chargeId, 'order.paid', 'paid', $request->all());
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Handle Efí PIX Automático (cobr) webhook.
     * Notificação de cobrança recorrente paga; configuração no painel Efí (webhookcobr).
     * Payload pode conter txid e status; ao identificar cobr paga, dispara ProcessPaymentWebhook para atualizar Order e Subscription.
     */
    public function pixRecorrente(Request $request): JsonResponse
    {
        $txid = $this->extractTxId($request);
        if ($txid === null || $txid === '') {
            $txid = $request->input('cobr.txid') ?? $request->input('txid');
            if (! is_string($txid) || $txid === '') {
                return response()->json(['message' => 'txid required'], 400);
            }
        }

        $order = Order::where('gateway', 'efi')->where('gateway_id', $txid)->first();
        if (! $order) {
            Log::debug('EfiWebhookController pixRecorrente: order not found', ['txid' => $txid]);
            return response()->json(['received' => true]);
        }

        if (! $this->verifyEfiWebhookSignature($order->tenant_id, $request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $status = $request->input('status') ?? $request->input('cobr.status') ?? 'CONCLUIDA';
        $statusNorm = is_string($status) ? strtoupper($status) : '';

        if (in_array($statusNorm, ['CONCLUIDA', 'LIQUIDADA', 'PAID', 'PAGO'], true)) {
            ProcessPaymentWebhook::dispatchSync('efi', $txid, 'order.paid', 'paid', $request->all());
        }

        return response()->json(['received' => true]);
    }

    /**
     * Verifica assinatura do webhook Efí (HMAC na URL). Se o tenant tiver webhook_hmac configurado, exige match.
     */
    private function verifyEfiWebhookSignature(?int $tenantId, Request $request): bool
    {
        $credential = GatewayCredential::resolveForPayment($tenantId, 'efi');
        if (! $credential) {
            return true;
        }
        $credentials = $credential->getDecryptedCredentials();
        $expectedHmac = $credentials['webhook_hmac'] ?? null;
        if ($expectedHmac === null || $expectedHmac === '') {
            return true;
        }
        $receivedHmac = $request->query('hmac');

        return is_string($receivedHmac) && hash_equals($expectedHmac, $receivedHmac);
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array<string, mixed>
     */
    private function buildEfiOptions(array $credentials): array
    {
        $certPath = $credentials['certificate_path'] ?? '';
        if ($certPath !== '' && is_file($certPath)) {
            $certPath = realpath($certPath) ?: $certPath;
        } else {
            $certPath = null;
        }
        $sandbox = isset($credentials['sandbox'])
            ? filter_var($credentials['sandbox'], FILTER_VALIDATE_BOOLEAN)
            : false;

        return [
            'client_id' => $credentials['client_id'] ?? '',
            'client_secret' => $credentials['client_secret'] ?? '',
            'certificate' => $certPath,
            'pwdCertificate' => $credentials['pwd_certificate'] ?? '',
            'sandbox' => $sandbox,
        ];
    }
}
