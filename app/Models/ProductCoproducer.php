<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class ProductCoproducer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const DURATION_ETERNAL = 'eternal';

    /** @var list<string> */
    public const DURATION_DAYS = ['30', '60', '90', '120'];

    protected $fillable = [
        'product_id',
        'inviter_user_id',
        'co_producer_user_id',
        'email',
        'status',
        'token',
        'commission_percent',
        'commission_on_direct_sales',
        'commission_on_affiliate_sales',
        'duration_preset',
        'starts_at',
        'ends_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'commission_on_direct_sales' => 'boolean',
            'commission_on_affiliate_sales' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function coProducer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'co_producer_user_id');
    }

    public function isActiveNow(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || $this->co_producer_user_id === null) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function applyAcceptance(User $user): void
    {
        $starts = now();
        $ends = null;
        if ($this->duration_preset !== self::DURATION_ETERNAL && in_array($this->duration_preset, self::DURATION_DAYS, true)) {
            $ends = $starts->copy()->addDays((int) $this->duration_preset);
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'co_producer_user_id' => $user->id,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'accepted_at' => $starts,
        ]);
    }

    /**
     * Após cadastro via convite: ativa se token e e-mail batem.
     */
    public static function tryActivateAfterRegistration(User $user, ?string $token): bool
    {
        if ($token === null || trim($token) === '' || ! $user->isInfoprodutor()) {
            return false;
        }

        $invitation = self::query()
            ->where('token', $token)
            ->where('status', self::STATUS_PENDING)
            ->with('product')
            ->first();

        if ($invitation === null) {
            return false;
        }

        if (self::normalizeEmail((string) $user->email) !== $invitation->email) {
            return false;
        }

        $productTenantId = (int) ($invitation->product?->tenant_id ?? 0);
        if ($productTenantId === (int) $user->tenant_id) {
            return false;
        }

        if (self::query()
            ->where('product_id', $invitation->product_id)
            ->where('co_producer_user_id', $user->id)
            ->where('status', self::STATUS_ACTIVE)
            ->where('id', '!=', $invitation->id)
            ->exists()) {
            return false;
        }

        $invitation->applyAcceptance($user);

        return true;
    }

    /**
     * @return array<int, array{tenant_id: int, gross: float, product_coproducer_id: int|null, role: string}>
     */
    public static function buildGrossSlicesForOrder(Order $order, float $grossTotal, bool $isAffiliateSale): array
    {
        $sellerTenantId = (int) $order->tenant_id;
        if ($sellerTenantId < 1 || $grossTotal <= 0) {
            return [['tenant_id' => $sellerTenantId, 'gross' => $grossTotal, 'product_coproducer_id' => null, 'role' => 'seller']];
        }

        $productId = $order->product_id;
        if ($productId === null || $productId === '') {
            return [['tenant_id' => $sellerTenantId, 'gross' => $grossTotal, 'product_coproducer_id' => null, 'role' => 'seller']];
        }

        $rows = static::query()
            ->where('product_id', $productId)
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('co_producer_user_id')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get();

        $eligible = $rows->filter(function (ProductCoproducer $row) use ($isAffiliateSale) {
            if ($isAffiliateSale) {
                return $row->commission_on_affiliate_sales;
            }

            return $row->commission_on_direct_sales;
        });

        if ($eligible->isEmpty()) {
            $slices = [[
                'tenant_id' => $sellerTenantId,
                'gross' => $grossTotal,
                'product_coproducer_id' => null,
                'role' => 'seller',
                'product_affiliate_enrollment_id' => null,
            ]];
            if ($isAffiliateSale) {
                $affSlice = self::affiliateGrossSliceFromOrder($order, $grossTotal);
                if ($affSlice !== null && (float) $affSlice['gross'] > 0.0001) {
                    $affGross = (float) $affSlice['gross'];
                    $slices[0]['gross'] = round(max(0, (float) $slices[0]['gross'] - $affGross), 2);
                    $slices[] = $affSlice;
                }
            }

            return $slices;
        }

        $sumPct = round((float) $eligible->sum('commission_percent'), 2);
        if ($sumPct > 100.01) {
            return [['tenant_id' => $sellerTenantId, 'gross' => $grossTotal, 'product_coproducer_id' => null, 'role' => 'seller']];
        }

        /** @var Collection<int, ProductCoproducer> $list */
        $list = $eligible->values();
        $coproducerSlices = [];
        $remaining = $grossTotal;
        $n = $list->count();

        foreach ($list as $idx => $row) {
            $pct = (float) $row->commission_percent;
            if ($pct <= 0) {
                continue;
            }
            $isLast = $idx === $n - 1;
            if ($n === 1) {
                $sliceGross = round($grossTotal * $pct / 100.0, 2);
            } else {
                $sliceGross = $isLast
                    ? round(max(0, $remaining), 2)
                    : round($grossTotal * $pct / 100.0, 2);
                if (! $isLast) {
                    $remaining = round($remaining - $sliceGross, 2);
                }
            }
            $coproducerTenant = (int) $row->co_producer_user_id;
            if ($coproducerTenant > 0 && $sliceGross > 0.0001) {
                $coproducerSlices[] = [
                    'tenant_id' => $coproducerTenant,
                    'gross' => $sliceGross,
                    'product_coproducer_id' => (int) $row->id,
                    'role' => 'coproducer',
                    'product_affiliate_enrollment_id' => null,
                ];
            }
        }

        $coproducerGrossTotal = round(array_sum(array_column($coproducerSlices, 'gross')), 2);
        $sellerGross = round(max(0, $grossTotal - $coproducerGrossTotal), 2);

        $slices = array_merge([
            [
                'tenant_id' => $sellerTenantId,
                'gross' => $sellerGross,
                'product_coproducer_id' => null,
                'role' => 'seller',
                'product_affiliate_enrollment_id' => null,
            ],
        ], $coproducerSlices);

        if ($isAffiliateSale) {
            $affSlice = self::affiliateGrossSliceFromOrder($order, $grossTotal);
            if ($affSlice !== null && (float) $affSlice['gross'] > 0.0001) {
                $affGross = (float) $affSlice['gross'];
                $slices[0]['gross'] = round(max(0, (float) $slices[0]['gross'] - $affGross), 2);
                $slices[] = $affSlice;
            }
        }

        return $slices;
    }

    /**
     * @return array{tenant_id: int, gross: float, product_coproducer_id: null, role: string, product_affiliate_enrollment_id: int}|null
     */
    private static function affiliateGrossSliceFromOrder(Order $order, float $grossTotal): ?array
    {
        $meta = $order->metadata ?? [];
        if (! is_array($meta) || empty($meta['affiliate_enrollment_id'])) {
            return null;
        }

        $enrollment = ProductAffiliateEnrollment::query()->find((int) $meta['affiliate_enrollment_id']);
        if ($enrollment === null || $enrollment->status !== ProductAffiliateEnrollment::STATUS_APPROVED) {
            return null;
        }

        $productId = $order->product_id;
        if ($productId === null || $productId === '') {
            return null;
        }
        // Comparar como string: orders.product_id e enrollments.product_id podem vir como int (SQLite) ou char(36).
        if ((string) $enrollment->product_id !== (string) $productId) {
            return null;
        }

        if (! empty($meta['affiliate_user_id']) && (int) $meta['affiliate_user_id'] !== (int) $enrollment->affiliate_user_id) {
            return null;
        }

        $product = $order->relationLoaded('product') ? $order->product : Product::query()->find($productId);
        if ($product === null || ! $product->affiliate_enabled) {
            return null;
        }

        $pct = (float) $product->affiliate_commission_percent;
        if ($pct <= 0) {
            return null;
        }

        $sellerTenantId = (int) $order->tenant_id;
        if ((int) $enrollment->affiliate_user_id === $sellerTenantId) {
            return null;
        }

        $gross = round($grossTotal * $pct / 100.0, 2);
        if ($gross <= 0) {
            return null;
        }

        return [
            'tenant_id' => (int) $enrollment->affiliate_user_id,
            'gross' => $gross,
            'product_coproducer_id' => null,
            'role' => 'affiliate',
            'product_affiliate_enrollment_id' => (int) $enrollment->id,
        ];
    }
}
