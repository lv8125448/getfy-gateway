<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SpedyIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpedyController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:512'],
            'environment' => ['nullable', 'string', 'in:production,sandbox'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $this->ensureProductIdsBelongToTenant($tenantId, $validated['product_ids'] ?? []);

        $integration = SpedyIntegration::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'api_key' => $validated['api_key'],
            'environment' => $validated['environment'] ?? SpedyIntegration::ENVIRONMENT_PRODUCTION,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['product_ids'])) {
            $integration->products()->sync($validated['product_ids']);
        }

        return response()->json([
            'integration' => $this->integrationToArray($integration),
        ], 201);
    }

    public function update(Request $request, SpedyIntegration $spedy): JsonResponse
    {
        $this->authorizeIntegration($spedy);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:512'],
            'environment' => ['nullable', 'string', 'in:production,sandbox'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['string', 'exists:products,id'],
        ]);

        $this->ensureProductIdsBelongToTenant($spedy->tenant_id, $validated['product_ids'] ?? []);

        $spedy->update([
            'name' => $validated['name'],
            'environment' => $validated['environment'] ?? SpedyIntegration::ENVIRONMENT_PRODUCTION,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->has('api_key')) {
            $spedy->api_key = $validated['api_key'] !== '' ? $validated['api_key'] : null;
            $spedy->save();
        }

        if (array_key_exists('product_ids', $validated)) {
            $spedy->products()->sync($validated['product_ids'] ?? []);
        }

        $spedy->load('products:id,name');

        return response()->json([
            'integration' => $this->integrationToArray($spedy),
        ]);
    }

    public function destroy(SpedyIntegration $spedy): JsonResponse
    {
        $this->authorizeIntegration($spedy);
        $spedy->products()->detach();
        $spedy->delete();

        return response()->json(null, 204);
    }

    private function authorizeIntegration(SpedyIntegration $integration): void
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

    private function integrationToArray(SpedyIntegration $i): array
    {
        $i->load('products:id,name');

        return [
            'id' => $i->id,
            'name' => $i->name,
            'is_active' => $i->is_active,
            'configured' => $i->api_key !== null && $i->api_key !== '',
            'environment' => $i->environment ?? SpedyIntegration::ENVIRONMENT_PRODUCTION,
            'product_ids' => $i->products->pluck('id')->values()->all(),
            'products' => $i->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
        ];
    }
}
