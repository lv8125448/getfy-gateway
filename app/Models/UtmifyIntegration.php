<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UtmifyIntegration extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'api_key',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }

        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActiveWithApiKey($query)
    {
        return $query->where('is_active', true)->whereNotNull('api_key');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'utmify_integration_product')
            ->withTimestamps();
    }

    /**
     * IDs de produtos vinculados à integração (string), para comparação estável com o pedido.
     *
     * @return array<int, string>
     */
    private function linkedProductIdsNormalized(): array
    {
        if ($this->relationLoaded('products')) {
            return $this->products->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()->all();
        }

        return $this->products()->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    /**
     * Verifica se esta integração se aplica ao product_id do pedido.
     * Se não tiver produtos vinculados, aplica a todos; senão só se o id coincidir (tipos normalizados).
     */
    public function appliesToProduct(?string $productId): bool
    {
        $linked = $this->linkedProductIdsNormalized();
        if ($linked === []) {
            return true;
        }
        if ($productId === null || $productId === '') {
            return false;
        }

        return in_array((string) $productId, $linked, true);
    }

    /**
     * Mesma regra de produtos vinculados, considerando também itens do pedido (ex.: order bumps).
     */
    public function appliesToOrder(Order $order): bool
    {
        $linked = $this->linkedProductIdsNormalized();
        if ($linked === []) {
            return true;
        }

        $order->loadMissing('orderItems');
        $candidates = collect([$order->product_id])
            ->merge($order->orderItems->pluck('product_id'))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        return $candidates !== [] && count(array_intersect($linked, $candidates)) > 0;
    }
}
