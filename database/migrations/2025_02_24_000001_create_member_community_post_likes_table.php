<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_community_post_likes')) {
            Schema::table('member_community_post_likes', function (Blueprint $table) {
                $table->unique(['member_community_post_id', 'user_id'], 'mc_post_likes_post_user_unique');
            });
            return;
        }
        Schema::create('member_community_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_community_post_id')->constrained('member_community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['member_community_post_id', 'user_id'], 'mc_post_likes_post_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_community_post_likes');
    }
};
