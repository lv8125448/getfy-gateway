<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLessonProgress extends Model
{
    protected $table = 'member_lesson_progress';

    protected $fillable = ['user_id', 'member_lesson_id', 'product_id', 'completed_at', 'progress_percent'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'progress_percent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(MemberLesson::class, 'member_lesson_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForProduct($query, int|string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
