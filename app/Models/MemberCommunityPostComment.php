<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCommunityPostComment extends Model
{
    protected $fillable = ['member_community_post_id', 'user_id', 'content'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(MemberCommunityPost::class, 'member_community_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
