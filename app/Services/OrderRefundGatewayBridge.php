<?php

namespace App\Services;

use App\Gateways\GatewayRegistry;
use App\Models\GatewayCredential;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Tenta estorno via API do adquirente quando o driver expõe refundTransaction.
 * Híbrido: se não houver suporte ou falhar, devolve skipped/failed para registro manual.
 */
class OrderRefundGatewayBridge
{
    /**
     * @return array{status: string, note: ?string}
     */
    public function tryRefund(Order $order): array
    {
        $gatewaySlug = $order->gateway;
        if ($gatewaySlug === null || $gatewaySlug === '') {
            return ['status' => 'skipped', 'note' => 'Pedido sem gateway registrado.'];
        }

        $tenantId = (int) $order->tenant_id;
        $credential = GatewayCredential::resolveForPayment($tenantId, $gatewaySlug);
        if (! $credential) {
            return ['status' => 'skipped', 'note' => 'Credencial do gateway não encontrada.'];
        }

        $credentials = $credential->getDecryptedCredentials();
        $driver = GatewayRegistry::driver($gatewaySlug);
        if (! $driver || ! is_callable([$driver, 'refundTransaction'])) {
            return ['status' => 'skipped', 'note' => 'Estorno automático não implementado para este gateway; conclua no adquirente se necessário.'];
        }

        $txId = (string) ($order->gateway_id ?? '');
        if ($txId === '') {
            return ['status' => 'skipped', 'note' => 'Sem ID de transação no gateway.'];
        }

        try {
            $result = $driver->refundTransaction($credentials, $txId, (float) $order->amount, (string) $order->id);
            $ok = $result['success'] ?? false;
            if ($ok) {
                return ['status' => 'gateway_ok', 'note' => $result['message'] ?? null];
            }

            return ['status' => 'failed', 'note' => $result['message'] ?? 'API de estorno retornou falha.'];
        } catch (\Throwable $e) {
            Log::warning('OrderRefundGatewayBridge: estorno API falhou.', [
                'order_id' => $order->id,
                'gateway' => $gatewaySlug,
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'note' => 'Erro na API: '.$e->getMessage()];
        }
    }
}
