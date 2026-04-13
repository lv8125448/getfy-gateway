<?php

namespace App\Http\Controllers\Webhooks;

use App\Gateways\GatewayRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Dispatcher genérico para webhooks de gateways registrados por plugins.
 * Rota: POST /webhooks/gateways/{slug}
 * Plugins registram o gateway com 'webhook_handler' => ClasseOuCallable no array passado a GatewayRegistry::register().
 * O handler deve aceitar (Request $request) e retornar Response|JsonResponse.
 */
class GenericGatewayWebhookController extends Controller
{
    public function __invoke(Request $request, string $slug): Response|JsonResponse
    {
        $gateway = GatewayRegistry::get($slug);
        if (! $gateway) {
            Log::debug('GenericGatewayWebhook: gateway not found', ['slug' => $slug]);
            return response()->json(['error' => 'Gateway not found'], 404);
        }

        $handler = $gateway['webhook_handler'] ?? null;
        if ($handler === null) {
            $driver = GatewayRegistry::driver($slug);
            if ($driver !== null && method_exists($driver, 'handleWebhook')) {
                return $driver->handleWebhook($request);
            }
            Log::debug('GenericGatewayWebhook: no handler for gateway', ['slug' => $slug]);
            return response()->json(['error' => 'Webhook not configured for this gateway'], 404);
        }

        if (is_callable($handler)) {
            return $handler($request, $slug);
        }

        if (is_string($handler)) {
            $instance = app($handler);
            if (method_exists($instance, 'handle')) {
                return $instance->handle($request, $slug);
            }
            if (is_callable($instance)) {
                return $instance($request, $slug);
            }
        }

        Log::warning('GenericGatewayWebhook: invalid handler', ['slug' => $slug]);
        return response()->json(['error' => 'Invalid webhook handler'], 500);
    }
}
