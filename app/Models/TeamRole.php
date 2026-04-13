<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamRole extends Model
{
    protected $table = 'team_roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'team_role_product', 'team_role_id', 'product_id')->withTimestamps();
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'team_role_id');
    }
}

