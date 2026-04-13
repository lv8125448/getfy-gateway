<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'product_id', 'product_offer_id', 'subscription_plan_id',
        'api_application_id', 'api_checkout_session_id',
        'status', 'amount', 'email', 'cpf', 'phone', 'customer_ip', 'coupon_code',
        'gateway', 'gateway_id', 'payment_method', 'approved_manually', 'metadata', 'period_start', 'period_end', 'is_renewal',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'is_renewal' => 'boolean',
            'approved_manually' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Dono do tenant (infoprodutor): `tenant_id` referencia `users.id` do infoprodutor. */
    public function tenantOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productOffer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function apiApplication(): BelongsTo
    {
        return $this->belongsTo(ApiApplication::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    public function checkoutSession(): HasOne
    {
        return $this->hasOne(CheckoutSession::class);
    }

    /**
     * Valor líquido exibido em relatórios: soma das linhas (produto + order bumps) ou, se não houver itens, orders.amount.
     */
    public function lineItemsTotalAmount(): float
    {
        $this->loadMissing('orderItems');

        if ($this->orderItems->isEmpty()) {
            return (float) $this->amount;
        }

        return round((float) $this->orderItems->sum(fn ($it) => (float) ($it->amount ?? 0)), 2);
    }

    public function getCheckoutSlug(): string
    {
        if ($this->productOffer && $this->productOffer->checkout_slug) {
            return $this->productOffer->checkout_slug;
        }
        if ($this->subscriptionPlan && $this->subscriptionPlan->checkout_slug) {
            return $this->subscriptionPlan->checkout_slug;
        }
        return $this->product?->checkout_slug ?? '';
    }

    /**
     * Rótulo para UI (vendas, export): PIX / Cartão / Boleto conforme o fluxo do checkout,
     * não o slug do gateway (ex.: mercadopago).
     */
    public function paymentMethodDisplayLabel(): string
    {
        $meta = $this->metadata ?? [];
        $m = isset($meta['checkout_payment_method']) ? strtolower((string) $meta['checkout_payment_method']) : '';

        return match ($m) {
            'pix' => 'PIX',
            'pix_auto' => 'PIX automático',
            'card' => 'Cartão',
            'boleto' => 'Boleto',
            default => self::gatewaySlugDisplayLabel($this->gateway),
        };
    }

    public static function gatewaySlugDisplayLabel(?string $gateway): string
    {
        if ($gateway === null || $gateway === '') {
            return 'Outro';
        }
        $g = strtolower($gateway);
        if (in_array($g, ['spacepag'], true) || str_contains($g, 'pix')) {
            return 'PIX';
        }
        if ($g === 'card' || str_contains($g, 'cartao') || str_contains($g, 'cartão') || str_contains($g, 'credito')) {
            return 'Cartão';
        }
        if ($g === 'boleto' || str_contains($g, 'boleto')) {
            return 'Boleto';
        }
        if ($g === 'manual') {
            return 'Manual';
        }

        return ucfirst($gateway);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $tenantId);
    }

    /**
     * Venda originada por afiliado (quando o módulo de afiliados gravar metadata).
     */
    public function isAffiliateSale(): bool
    {
        $m = $this->metadata ?? [];

        return is_array($m) && ! empty($m['affiliate_user_id']);
    }

    /**
     * Attach buyer to main product and order bump products (same rules as public checkout after payment).
     */
    public function grantPurchasedProductAccessToBuyer(): void
    {
        $this->loadMissing('orderItems.product', 'product');
        if ($this->product) {
            $this->product->users()->syncWithoutDetaching([$this->user_id]);
        }
        foreach ($this->orderItems as $item) {
            if ($item->product) {
                $item->product->users()->syncWithoutDetaching([$this->user_id]);
            }
        }
    }
}
