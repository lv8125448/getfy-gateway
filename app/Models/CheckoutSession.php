<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    public const STEP_VISIT = 'visit';

    public const STEP_FORM_STARTED = 'form_started';

    public const STEP_FORM_FILLED = 'form_filled';

    public const STEP_CONVERTED = 'converted';

    protected $fillable = [
        'tenant_id', 'product_id', 'product_offer_id', 'subscription_plan_id',
        'checkout_slug', 'session_token', 'step', 'email', 'name',
        'customer_ip', 'order_id', 'utm_source', 'utm_medium', 'utm_campaign',
        'abandoned_webhook_fired_at',
    ];

    protected function casts(): array
    {
        return [
            'abandoned_webhook_fired_at' => 'datetime',
        ];
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
        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $tenantId);
    }
}
