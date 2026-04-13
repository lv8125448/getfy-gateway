<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAST_DUE = 'past_due';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'product_id',
        'subscription_plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'saved_payment_method_id',
        'gateway_subscription_id',
        'renewal_token',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'date',
            'current_period_end' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $sub): void {
            if (empty($sub->renewal_token)) {
                $sub->renewal_token = static::generateRenewalToken();
            }
        });
    }

    public static function generateRenewalToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('renewal_token', $token)->exists());

        return $token;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function savedPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(SavedPaymentMethod::class);
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
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
