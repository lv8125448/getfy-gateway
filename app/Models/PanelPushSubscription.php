<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelPushSubscription extends Model
{
    protected $fillable = ['user_id', 'tenant_id', 'endpoint', 'keys', 'user_agent'];

    protected function casts(): array
    {
        return ['keys' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
