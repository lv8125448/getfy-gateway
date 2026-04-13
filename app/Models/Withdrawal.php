<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'amount',
        'fee_amount',
        'net_amount',
        'bucket',
        'status',
        'notes',
        'currency',
        'payout_provider',
        'payout_external_id',
        'payout_meta',
        'payout_manual',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'payout_meta' => 'array',
            'payout_manual' => 'boolean',
        ];
    }

    public function tenantOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
