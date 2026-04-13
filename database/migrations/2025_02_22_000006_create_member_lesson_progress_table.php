<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_lesson_id')->constrained('member_lessons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'member_lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_lesson_progress');
    }
};
