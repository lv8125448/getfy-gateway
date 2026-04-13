<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberSection extends Model
{
    protected $fillable = ['product_id', 'title', 'position', 'cover_mode', 'section_type'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(MemberModule::class, 'member_section_id')->orderBy('position');
    }
}
