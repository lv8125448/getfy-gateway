<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'bearer_token',
        'events',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'bearer_token' => 'encrypted',
        ];
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }

        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this webhook listens to the given event class.
     */
    public function listensTo(string $eventClass): bool
    {
        $events = $this->events ?? [];

        return in_array($eventClass, $events, true);
    }

    /**
     * Check if this webhook should fire for a given product.
     *
     * @param  int|string|null  $productId  Product ID (int or UUID string)
     */
    public function shouldFireForProduct(mixed $productId): bool
    {
        if ($productId === null || $productId === '') {
            return true;
        }

        $productIds = $this->products()->pluck('products.id')->map(fn ($id) => (string) $id)->toArray();

        if (empty($productIds)) {
            return true;
        }

        return in_array((string) $productId, $productIds, true);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class)->orderByDesc('created_at');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_webhook')
            ->withTimestamps();
    }
}
