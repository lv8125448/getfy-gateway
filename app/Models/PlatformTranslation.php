<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformTranslation extends Model
{
    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
    ];
}
