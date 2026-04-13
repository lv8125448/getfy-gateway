<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SpedyIntegration extends Model
{
    public const ENVIRONMENT_PRODUCTION = 'production';

    public const ENVIRONMENT_SANDBOX = 'sandbox';

    protected $fillable = [
        'tenant_id',
        'name',
        'api_key',
        'environment',
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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'spedy_integration_product')
            ->withTimestamps();
    }

    /**
     * Verifica se esta integração se aplica ao product_id do pedido.
     * Se não tiver produtos vinculados, aplica a todos; senão só ao product_id.
     */
    public function appliesToProduct(?string $productId): bool
    {
        $productIds = $this->products()->pluck('products.id')->all();

        if (count($productIds) === 0) {
            return true;
        }

        return $productId !== null && in_array($productId, $productIds, true);
    }
}
