<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    public const TYPE_CREDIT_SALE = 'credit_sale';

    /** Crédito ainda em liquidação (D+N / reserva); saldo em pending_* até clears_at. */
    public const TYPE_CREDIT_SALE_PENDING = 'credit_sale_pending';

    /** Estorno do crédito de venda (ação manual ou gateway). */
    public const TYPE_DEBIT_REFUND = 'debit_refund';

    /** Valor da venda bloqueado em saldo pendente (MED / contestação). */
    public const TYPE_MED_HOLD = 'med_hold';

    public const TYPE_WITHDRAWAL_REQUEST = 'withdrawal_request';

    public const TYPE_WITHDRAWAL_COMPLETE = 'withdrawal_complete';

    public const TYPE_WITHDRAWAL_REFUND = 'withdrawal_refund';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'withdrawal_id',
        'bucket',
        'type',
        'amount_gross',
        'amount_fee',
        'amount_net',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_gross' => 'decimal:2',
            'amount_fee' => 'decimal:2',
            'amount_net' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
