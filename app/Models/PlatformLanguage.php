<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformLanguage extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'is_default' => 'bool',
            'sort_order' => 'int',
        ];
    }
}
