<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCertificateIssued extends Model
{
    protected $table = 'member_certificates_issued';

    protected $fillable = ['user_id', 'product_id', 'issued_at', 'completion_percent'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'completion_percent' => 'integer',
        ];
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
