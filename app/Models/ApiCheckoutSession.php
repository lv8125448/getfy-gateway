<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCheckoutSession extends Model
{
    protected $fillable = [
        'api_application_id',
        'tenant_id',
        'session_token',
        'customer',
        'amount',
        'currency',
        'product_id',
        'product_offer_id',
        'subscription_plan_id',
        'metadata',
        'return_url',
        'expires_at',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'customer' => 'array',
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function apiApplication(): BelongsTo
    {
        return $this->belongsTo(ApiApplication::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }
        return $query->where('tenant_id', $tenantId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
