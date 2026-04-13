<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CademiIntegration extends Model
{
    public const DELIVERY_METHOD_POSTBACK_CUSTOM = 'postback_custom';
    public const DELIVERY_METHOD_TAGS_API = 'tags_api';

    protected $fillable = [
        'tenant_id',
        'name',
        'base_url',
        'api_key',
        'delivery_method',
        'postback_token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'postback_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $tenantId);
    }

    public function scopeActiveConfigured($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('postback_token')
                    ->orWhereNotNull('api_key');
            });
    }

    public function isPostbackCustom(): bool
    {
        // UX simplificada: se tiver token, sempre usa postback.
        if (trim((string) ($this->postback_token ?? '')) !== '') {
            return true;
        }

        return ($this->delivery_method ?: self::DELIVERY_METHOD_POSTBACK_CUSTOM) === self::DELIVERY_METHOD_POSTBACK_CUSTOM;
    }

    public function isTagsApi(): bool
    {
        // UX simplificada: só usa API TAG se não tiver token e tiver api_key.
        if (trim((string) ($this->postback_token ?? '')) !== '') {
            return false;
        }
        if (trim((string) ($this->api_key ?? '')) !== '') {
            return true;
        }

        return ($this->delivery_method ?: self::DELIVERY_METHOD_POSTBACK_CUSTOM) === self::DELIVERY_METHOD_TAGS_API;
    }

    /**
     * Product-level mapping (optional).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'cademi_integration_product')
            ->withPivot(['cademi_tag_id', 'cademi_produto_id', 'cademi_produto_ids'])
            ->withTimestamps();
    }

    /**
     * Offer-level mapping (optional).
     */
    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(ProductOffer::class, 'cademi_integration_product_offer')
            ->withPivot(['cademi_tag_id', 'cademi_produto_id', 'cademi_produto_ids'])
            ->withTimestamps();
    }

    /**
     * Plan-level mapping (optional).
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'cademi_integration_subscription_plan')
            ->withPivot(['cademi_tag_id', 'cademi_produto_id', 'cademi_produto_ids'])
            ->withTimestamps();
    }

    /**
     * Resolve best mapping for an order (priority: plan > offer > product).
     *
     * @return array{scope: 'plan'|'offer'|'product', tag_id: int|null, produto_id: int|null, produto_ids?: array<int,int>}|null
     */
    public function resolveMappingForOrder(Order $order): ?array
    {
        $order->loadMissing(['subscriptionPlan', 'productOffer', 'product']);

        if ($order->subscription_plan_id) {
            $row = $this->plans()
                ->where('subscription_plans.id', $order->subscription_plan_id)
                ->first();
            if ($row) {
                $produtoIds = $this->decodeProdutoIds($row->pivot?->cademi_produto_ids) ?: ($row->pivot?->cademi_produto_id ? [(int) $row->pivot->cademi_produto_id] : []);
                return [
                    'scope' => 'plan',
                    'tag_id' => $row->pivot?->cademi_tag_id ? (int) $row->pivot->cademi_tag_id : null,
                    'produto_id' => $row->pivot?->cademi_produto_id ? (int) $row->pivot->cademi_produto_id : null,
                    'produto_ids' => $produtoIds,
                ];
            }
        }

        if ($order->product_offer_id) {
            $row = $this->offers()
                ->where('product_offers.id', $order->product_offer_id)
                ->first();
            if ($row) {
                $produtoIds = $this->decodeProdutoIds($row->pivot?->cademi_produto_ids) ?: ($row->pivot?->cademi_produto_id ? [(int) $row->pivot->cademi_produto_id] : []);
                return [
                    'scope' => 'offer',
                    'tag_id' => $row->pivot?->cademi_tag_id ? (int) $row->pivot->cademi_tag_id : null,
                    'produto_id' => $row->pivot?->cademi_produto_id ? (int) $row->pivot->cademi_produto_id : null,
                    'produto_ids' => $produtoIds,
                ];
            }
        }

        if ($order->product_id) {
            $row = $this->products()
                ->where('products.id', (string) $order->product_id)
                ->first();
            if ($row) {
                $produtoIds = $this->decodeProdutoIds($row->pivot?->cademi_produto_ids) ?: ($row->pivot?->cademi_produto_id ? [(int) $row->pivot->cademi_produto_id] : []);
                return [
                    'scope' => 'product',
                    'tag_id' => $row->pivot?->cademi_tag_id ? (int) $row->pivot->cademi_tag_id : null,
                    'produto_id' => $row->pivot?->cademi_produto_id ? (int) $row->pivot->cademi_produto_id : null,
                    'produto_ids' => $produtoIds,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function decodeProdutoIds($raw): array
    {
        if ($raw === null) {
            return [];
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }

        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            $ids = [];
            foreach ($decoded as $v) {
                $n = (int) $v;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
            return array_values(array_unique($ids));
        }

        // fallback: allow comma/semicolon separated values
        $parts = preg_split('/[;,\\s]+/', $s) ?: [];
        $ids = [];
        foreach ($parts as $p) {
            $n = (int) $p;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
        return array_values(array_unique($ids));
    }
}

