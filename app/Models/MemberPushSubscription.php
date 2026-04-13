<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPushSubscription extends Model
{
    protected $fillable = ['user_id', 'product_id', 'endpoint', 'keys', 'user_agent'];

    protected function casts(): array
    {
        return ['keys' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
