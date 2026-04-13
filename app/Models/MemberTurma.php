<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MemberTurma extends Model
{
    protected $table = 'member_turmas';

    protected $fillable = ['product_id', 'name', 'description', 'start_date', 'end_date', 'position'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_turma_user', 'member_turma_id', 'user_id')->withTimestamps();
    }
}
