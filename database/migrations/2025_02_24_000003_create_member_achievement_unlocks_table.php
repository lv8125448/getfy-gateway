<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_achievement_unlocks')) {
            Schema::create('member_achievement_unlocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('achievement_id', 64);
                $table->timestamp('unlocked_at');
                $table->timestamps();
                $table->unique(['user_id', 'product_id', 'achievement_id'], 'member_ach_unlocks_user_product_ach_unique');
            });
            return;
        }
        $indexExists = collect(DB::select("SHOW INDEX FROM member_achievement_unlocks WHERE Key_name = 'member_ach_unlocks_user_product_ach_unique'"))->isNotEmpty();
        if (! $indexExists) {
            Schema::table('member_achievement_unlocks', function (Blueprint $table) {
                $table->unique(['user_id', 'product_id', 'achievement_id'], 'member_ach_unlocks_user_product_ach_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_achievement_unlocks');
    }
};
