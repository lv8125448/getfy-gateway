<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAreaDomain extends Model
{
    public const TYPE_PATH = 'path';

    public const TYPE_SUBDOMAIN = 'subdomain';

    public const TYPE_CUSTOM = 'custom';

    protected $fillable = ['product_id', 'type', 'value'];

    public static function normalizeCustomHost(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $host = strtolower(trim($value));
        if ($host === '') {
            return null;
        }

        $host = (string) preg_replace('#^https?://#', '', $host);
        $host = explode('/', $host)[0] ?? '';
        $host = rtrim(trim($host), '.');

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
