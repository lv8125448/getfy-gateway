<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UtmifyIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtmifyController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:512'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $this->ensureProductIdsBelongToTenant($tenantId, $validated['product_ids'] ?? []);

        $integration = UtmifyIntegration::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'api_key' => $validated['api_key'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['product_ids'])) {
            $integration->products()->sync($validated['product_ids']);
        }

        return response()->json([
            'integration' => $this->integrationToArray($integration),
        ], 201);
    }

    public function update(Request $request, UtmifyIntegration $utmify): JsonResponse
    {
        $this->authorizeIntegration($utmify);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $this->ensureProductIdsBelongToTenant($utmify->tenant_id, $validated['product_ids'] ?? []);

        $utmify->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->has('api_key')) {
            $utmify->api_key = $validated['api_key'] !== '' ? $validated['api_key'] : null;
            $utmify->save();
        }

        if (array_key_exists('product_ids', $validated)) {
            $utmify->products()->sync($validated['product_ids'] ?? []);
        }

        $utmify->load('products:id,name');

        return response()->json([
            'integration' => $this->integrationToArray($utmify),
        ]);
    }

    public function destroy(UtmifyIntegration $utmify): JsonResponse
    {
        $this->authorizeIntegration($utmify);
        $utmify->products()->detach();
        $utmify->delete();

        return response()->json(null, 204);
    }

    private function authorizeIntegration(UtmifyIntegration $integration): void
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

    private function integrationToArray(UtmifyIntegration $i): array
    {
        $i->load('products:id,name');

        return [
            'id' => $i->id,
            'name' => $i->name,
            'is_active' => $i->is_active,
            'configured' => $i->api_key !== null && $i->api_key !== '',
            'product_ids' => $i->products->pluck('id')->values()->all(),
            'products' => $i->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
        ];
    }
}
