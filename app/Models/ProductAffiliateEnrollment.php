<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductAffiliateEnrollment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'product_id',
        'affiliate_user_id',
        'status',
        'public_ref',
        'conversion_pixels',
    ];

    protected function casts(): array
    {
        return [
            'conversion_pixels' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affiliate_user_id');
    }

    public static function generatePublicRef(): string
    {
        do {
            $ref = Str::lower(Str::random(12));
        } while (self::query()->where('public_ref', $ref)->exists());

        return $ref;
    }

    public function ensurePublicRef(): void
    {
        if ($this->public_ref !== null && $this->public_ref !== '') {
            return;
        }
        $this->public_ref = self::generatePublicRef();
        $this->save();
    }

    /**
     * Resolve enrollment for checkout: approved ref matches product and affiliate user.
     */
    public static function findApprovedByRefForProduct(string $ref, Product $product): ?self
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        return self::query()
            ->where('public_ref', $ref)
            ->where('product_id', $product->id)
            ->where('status', self::STATUS_APPROVED)
            ->first();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
