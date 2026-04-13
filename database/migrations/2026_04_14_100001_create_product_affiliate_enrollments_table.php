<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_affiliate_enrollments', function (Blueprint $table) {
            $table->id();
            // Mesmo tipo que products.id (char(36) no PostgreSQL após migração UUID — não usar uuid() nativo).
            $table->string('product_id', 36);
            $table->unsignedBigInteger('affiliate_user_id');
            $table->string('status', 32)->default('pending');
            $table->string('public_ref', 32)->nullable()->unique();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('affiliate_user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['product_id', 'affiliate_user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_affiliate_enrollments');
    }
};
