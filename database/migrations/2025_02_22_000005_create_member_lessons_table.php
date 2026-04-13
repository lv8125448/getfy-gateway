<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_module_id')->constrained('member_modules')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 32)->default('video'); // video, link, pdf, text
            $table->text('content_url')->nullable();
            $table->text('content_text')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_free')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_lessons');
    }
};
