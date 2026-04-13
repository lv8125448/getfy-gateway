<?php

namespace App\Services;

use App\Models\MemberComment;
use App\Models\Product;
use App\Models\User;

class MemberCommentService
{
    public function create(Product $product, User $user, string $content, ?int $lessonId = null, ?int $parentId = null, ?string $initialStatus = null): MemberComment
    {
        $status = $initialStatus === MemberComment::STATUS_APPROVED
            ? MemberComment::STATUS_APPROVED
            : MemberComment::STATUS_PENDING;

        return MemberComment::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'member_lesson_id' => $lessonId,
            'parent_id' => $parentId,
            'content' => $content,
            'status' => $status,
        ]);
    }

    public function approve(MemberComment $comment, User $reviewer): void
    {
        $comment->update([
            'status' => MemberComment::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);
    }

    public function reject(MemberComment $comment, User $reviewer): void
    {
        $comment->update([
            'status' => MemberComment::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);
    }
}
