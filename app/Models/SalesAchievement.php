<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesAchievement extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'threshold',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'sort_order' => 'int',
            'is_active' => 'bool',
        ];
    }
}
