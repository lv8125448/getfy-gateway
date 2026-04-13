<?php

namespace Plugins\ExampleGateway;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handler de webhook de exemplo para o plugin Example Gateway.
 * A URL de webhook é: POST /webhooks/gateways/example-gateway
 */
class ExampleGatewayWebhookHandler
{
    public function handle(Request $request, string $slug): JsonResponse
    {
        // Exemplo: apenas responde recebido. Em produção, processaria o payload e atualizaria o pedido.
        return response()->json(['received' => true]);
    }
}
