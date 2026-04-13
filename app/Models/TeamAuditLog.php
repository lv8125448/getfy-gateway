<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamAuditLog extends Model
{
    protected $table = 'team_audit_logs';

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

