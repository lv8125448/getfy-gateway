<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KycDocument extends Model
{
    public const KIND_RG_FRONT = 'rg_front';

    public const KIND_RG_BACK = 'rg_back';

    public const KIND_CNPJ_CARD = 'cnpj_card';

    public const KIND_SOCIAL_CONTRACT = 'social_contract';

    /** PJ: um único arquivo — cartão CNPJ ou contrato social. */
    public const KIND_COMPANY_DOCUMENT = 'company_document';

    protected $fillable = [
        'user_id',
        'public_token',
        'kind',
        'disk_path',
        'original_mime',
        'size_bytes',
    ];

    protected static function booted(): void
    {
        static::creating(function (KycDocument $doc) {
            if (empty($doc->public_token)) {
                $doc->public_token = (string) Str::uuid();
            }
        });
    }

    /** URL pública do painel usa token UUID — não expõe id sequencial. */
    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
