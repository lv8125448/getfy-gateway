<?php

namespace App\Http\Controllers;

use App\Models\CademiIntegration;
use App\Models\Product;
use App\Services\CademiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CademiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'string', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'postback_token' => ['nullable', 'string', 'max:512'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $this->ensureProductIdsBelongToTenant($tenantId, $validated['product_ids'] ?? []);

        $hasToken = ! empty(trim((string) ($validated['postback_token'] ?? '')));
        if (! $hasToken) {
            abort(422, 'Informe o Token de Postback da Cademí.');
        }

        // UX simplificada: sempre postback (API Key fica apenas como legado/extra).
        $deliveryMethod = CademiIntegration::DELIVERY_METHOD_POSTBACK_CUSTOM;

        $integration = CademiIntegration::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'delivery_method' => $deliveryMethod,
            'api_key' => $validated['api_key'] ?? null,
            'postback_token' => $validated['postback_token'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['product_ids'])) {
            // mapping básico (sem tag) para “atribuir produtos” na listagem; o vínculo de tag será configurado no produto.
            $integration->products()->sync($validated['product_ids']);
        }

        return response()->json([
            'integration' => $this->integrationToArray($integration),
        ], 201);
    }

    public function update(Request $request, CademiIntegration $cademi): JsonResponse
    {
        $this->authorizeIntegration($cademi);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'string', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'postback_token' => ['nullable', 'string', 'max:512'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $this->ensureProductIdsBelongToTenant($cademi->tenant_id, $validated['product_ids'] ?? []);

        $cademi->update([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->has('api_key')) {
            $cademi->api_key = $validated['api_key'] !== '' ? $validated['api_key'] : null;
            $cademi->save();
        }

        if ($request->has('postback_token')) {
            $cademi->postback_token = ($validated['postback_token'] ?? '') !== '' ? $validated['postback_token'] : null;
            $cademi->save();
        }

        $hasToken = trim((string) ($cademi->postback_token ?? '')) !== '';
        if (! $hasToken) {
            abort(422, 'Informe o Token de Postback da Cademí.');
        }

        $cademi->delivery_method = CademiIntegration::DELIVERY_METHOD_POSTBACK_CUSTOM;
        $cademi->save();

        if (array_key_exists('product_ids', $validated)) {
            $cademi->products()->sync($validated['product_ids'] ?? []);
        }

        $cademi->load('products:id,name');

        return response()->json([
            'integration' => $this->integrationToArray($cademi),
        ]);
    }

    public function destroy(CademiIntegration $cademi): JsonResponse
    {
        $this->authorizeIntegration($cademi);
        $cademi->products()->detach();
        $cademi->offers()->detach();
        $cademi->plans()->detach();
        $cademi->delete();

        return response()->json(null, 204);
    }

    public function tags(CademiIntegration $cademi): JsonResponse
    {
        $this->authorizeIntegration($cademi);
        if (! $cademi->is_active || ! $cademi->api_key) {
            return response()->json(['tags' => []]);
        }

        $service = new CademiService($cademi->base_url, (string) $cademi->api_key);
        $tags = $service->listTags();

        return response()->json(['tags' => $tags]);
    }

    private function authorizeIntegration(CademiIntegration $integration): void
    {
        $tenantId = auth()->user()->tenant_id;
        if ($integration->tenant_id !== $tenantId) {
            abort(404);
        }
    }

    /**
     * @param  array<int, string>  $productIds
     */
    private function ensureProductIdsBelongToTenant(?int $tenantId, array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }
        $count = Product::forTenant($tenantId)->whereIn('id', $productIds)->count();
        if ($count !== count($productIds)) {
            abort(422, 'Um ou mais produtos não pertencem ao seu tenant.');
        }
    }

    private function integrationToArray(CademiIntegration $i): array
    {
        $i->load('products:id,name');

        return [
            'id' => $i->id,
            'name' => $i->name,
            'base_url' => $i->base_url,
            'is_active' => $i->is_active,
            'delivery_method' => $i->delivery_method ?? CademiIntegration::DELIVERY_METHOD_POSTBACK_CUSTOM,
            'configured' => (trim((string) ($i->postback_token ?? '')) !== '')
                || (trim((string) ($i->api_key ?? '')) !== ''),
            'api_key' => $i->api_key ?? '',
            'postback_token' => $i->postback_token ?? '',
            'product_ids' => $i->products->pluck('id')->values()->all(),
            'products' => $i->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
        ];
    }
}

