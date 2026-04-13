<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'code',
        'type',
        'value',
        'min_amount',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'used_count' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Aplica o cupom ao preço do produto. Retorna null se inválido.
     *
     * @return array{discount_amount: float, final_price: float}|null
     */
    public function applyTo(Product $product, float $price): ?array
    {
        if (! $this->is_active) {
            return null;
        }
        if ($this->valid_from && $this->valid_from->isFuture()) {
            return null;
        }
        if ($this->valid_until && $this->valid_until->isPast()) {
            return null;
        }
        if ($this->max_uses !== null && (int) $this->used_count >= (int) $this->max_uses) {
            return null;
        }
        if ($this->min_amount !== null && $price < (float) $this->min_amount) {
            return null;
        }
        $linked = $this->products()->where('products.id', $product->id)->exists();
        if (! $linked) {
            return null;
        }

        $value = (float) $this->value;
        $discount = $this->type === self::TYPE_PERCENT
            ? round($price * $value / 100, 2)
            : min($value, $price);
        $finalPrice = max(0, round($price - $discount, 2));

        return [
            'discount_amount' => round($discount, 2),
            'final_price' => $finalPrice,
        ];
    }
}
