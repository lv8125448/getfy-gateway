<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberComment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'product_id',
        'user_id',
        'member_lesson_id',
        'parent_id',
        'content',
        'status',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(MemberLesson::class, 'member_lesson_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MemberComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(MemberComment::class, 'parent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForProduct($query, int|string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
