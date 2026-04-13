<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Support\WebhookUrlValidator;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $webhooks = Webhook::forTenant($tenantId)
            ->with('products:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Webhook $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'url' => $w->url,
                'has_bearer_token' => (bool) $w->bearer_token,
                'events' => $w->events ?? [],
                'is_active' => $w->is_active,
                'products' => $w->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ]);

        $webhookEvents = config('webhook_events.events', []);

        $products = \App\Models\Product::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
            ->toArray();

        return response()->json([
            'webhooks' => $webhooks,
            'webhook_events' => $webhookEvents,
            'products' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'bearer_token' => ['nullable', 'string', 'max:1024'],
            'events' => ['required', 'array'],
            'events.*' => [Rule::in(array_keys(config('webhook_events.events', [])))],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['required', 'string', 'exists:products,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;

        $webhook = Webhook::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'bearer_token' => $validated['bearer_token'] ?? null,
            'events' => array_values(array_unique($validated['events'])),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['product_ids'])) {
            $webhook->products()->sync($validated['product_ids']);
        }

        $webhook->load('products:id,name');

        return response()->json([
            'webhook' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'has_bearer_token' => (bool) $webhook->bearer_token,
                'events' => $webhook->events ?? [],
                'is_active' => $webhook->is_active,
                'products' => $webhook->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ],
        ], 201);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($webhook);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'bearer_token' => ['nullable', 'string', 'max:1024'],
            'events' => ['required', 'array'],
            'events.*' => [Rule::in(array_keys(config('webhook_events.events', [])))],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['required', 'string', 'exists:products,id'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => array_values(array_unique($validated['events'])),
            'is_active' => $validated['is_active'] ?? true,
        ];
        if (array_key_exists('bearer_token', $validated) && (string) $validated['bearer_token'] !== '') {
            $updateData['bearer_token'] = $validated['bearer_token'];
        }

        $webhook->update($updateData);

        if (array_key_exists('product_ids', $validated)) {
            $webhook->products()->sync($validated['product_ids'] ?? []);
        }

        $webhook->load('products:id,name');

        return response()->json([
            'webhook' => [
                'id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'has_bearer_token' => (bool) $webhook->bearer_token,
                'events' => $webhook->events ?? [],
                'is_active' => $webhook->is_active,
                'products' => $webhook->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->toArray(),
            ],
        ]);
    }

    public function destroy(Webhook $webhook): Response
    {
        $this->authorizeWebhook($webhook);

        $webhook->delete();

        return response()->noContent();
    }

    public function test(Request $request, Webhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($webhook);

        $events = config('webhook_events.events', []);
        $eventClass = $request->input('event');
        if ($eventClass && ! isset($events[$eventClass])) {
            $eventClass = null;
        }
        if (! $eventClass) {
            $eventClass = array_key_first($events) ?: config('webhook_events.test_event', 'webhook.test');
        }

        $eventLabel = $events[$eventClass] ?? config('webhook_events.test_event_label', 'Evento de teste');
        $eventSlugs = config('webhook_events.event_slugs', []);
        $eventSlug = $eventSlugs[$eventClass] ?? str_replace('\\', '.', $eventClass);

        $payload = [
            'test' => true,
            'message' => 'Este é um evento de teste disparado manualmente.',
            'webhook_name' => $webhook->name,
            'webhook_id' => $webhook->id,
            'customer' => [
                'name' => 'Cliente Exemplo',
                'email' => 'exemplo@email.com',
                'phone' => '11999999999',
                'cpf' => '12345678900',
            ],
            'checkout_link' => rtrim(config('app.url'), '/') . '/c/exemplo-checkout',
        ];

        if ($eventSlug === 'pix_gerado') {
            $payload['pix'] = [
                'qrcode' => 'data:image/png;base64,iVBORw0KGgo=',
                'copy_paste' => '00020126580014br.gov.bcb.pix...',
                'transaction_id' => 'txid-exemplo-teste',
            ];
        }

        $body = [
            'event' => $eventSlug,
            'event_label' => $eventLabel,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            WebhookUrlValidator::assertAllowed((string) $webhook->url);
        } catch (InvalidArgumentException $e) {
            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event' => $eventSlug,
                'event_label' => $eventLabel,
                'request_payload' => array_merge($body, ['blocked' => true]),
                'response_status' => null,
                'response_body' => null,
                'success' => false,
                'error_message' => 'URL bloqueada: '.$e->getMessage(),
                'source' => 'test',
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $httpRequest = Http::timeout(15)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody(json_encode($body), 'application/json');

        if ($webhook->bearer_token) {
            $httpRequest = $httpRequest->withToken($webhook->bearer_token);
        }

        try {
            $response = $httpRequest->post($webhook->url);
            $responseStatus = $response->status();
            $responseBody = $response->body();
            $success = $response->successful();

            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event' => $eventSlug,
                'event_label' => $eventLabel,
                'request_payload' => $body,
                'response_status' => $responseStatus,
                'response_body' => strlen($responseBody) > 2000 ? substr($responseBody, 0, 2000) . '…' : $responseBody,
                'success' => $success,
                'error_message' => $success ? null : 'HTTP ' . $responseStatus,
                'source' => 'test',
            ]);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Evento de teste enviado com sucesso. Verifique se sua URL recebeu o payload.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'A URL retornou status ' . $responseStatus . '. Verifique se o endpoint está configurado corretamente.',
            ], 422);
        } catch (\Throwable $e) {
            WebhookLog::create([
                'webhook_id' => $webhook->id,
                'event' => $eventSlug,
                'event_label' => $eventLabel,
                'request_payload' => $body,
                'response_status' => null,
                'response_body' => null,
                'success' => false,
                'error_message' => $e->getMessage(),
                'source' => 'test',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logs(Webhook $webhook): JsonResponse
    {
        $this->authorizeWebhook($webhook);

        $logs = $webhook->logs()->limit(50)->get()->map(fn (WebhookLog $log) => [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => $log->event_label,
            'response_status' => $log->response_status,
            'success' => $log->success,
            'error_message' => $log->error_message,
            'source' => $log->source,
            'created_at' => $log->created_at->toIso8601String(),
        ]);

        return response()->json(['logs' => $logs]);
    }

    public function showLog(Webhook $webhook, int $log): JsonResponse
    {
        $this->authorizeWebhook($webhook);

        $logEntry = $webhook->logs()->findOrFail($log);

        return response()->json([
            'log' => [
                'id' => $logEntry->id,
                'event' => $logEntry->event,
                'event_label' => $logEntry->event_label,
                'request_payload' => $logEntry->request_payload,
                'response_status' => $logEntry->response_status,
                'response_body' => $logEntry->response_body,
                'success' => $logEntry->success,
                'error_message' => $logEntry->error_message,
                'source' => $logEntry->source,
                'created_at' => $logEntry->created_at->toIso8601String(),
            ],
        ]);
    }

    private function authorizeWebhook(Webhook $webhook): void
    {
        $tenantId = auth()->user()->tenant_id;

        if ($webhook->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
