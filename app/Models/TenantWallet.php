<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantWallet extends Model
{
    protected $fillable = [
        'tenant_id',
        'available_balance',
        'pending_balance',
        'available_pix',
        'available_card',
        'available_boleto',
        'pending_pix',
        'pending_card',
        'pending_boleto',
        'currency',
        'admin_withdrawal_blocked',
        'admin_blocked_amount',
        'admin_block_until',
        'admin_block_note',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'available_pix' => 'decimal:2',
            'available_card' => 'decimal:2',
            'available_boleto' => 'decimal:2',
            'pending_pix' => 'decimal:2',
            'pending_card' => 'decimal:2',
            'pending_boleto' => 'decimal:2',
            'admin_withdrawal_blocked' => 'boolean',
            'admin_blocked_amount' => 'decimal:2',
            'admin_block_until' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'id');
    }
}
