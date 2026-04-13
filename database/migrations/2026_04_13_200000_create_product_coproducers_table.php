<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_coproducers', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->unsignedBigInteger('inviter_user_id');
            $table->unsignedBigInteger('co_producer_user_id')->nullable();
            $table->string('email');
            $table->string('status', 32)->default('pending');
            $table->string('token', 64)->unique();
            $table->decimal('commission_percent', 5, 2);
            $table->boolean('commission_on_direct_sales')->default(true);
            $table->boolean('commission_on_affiliate_sales')->default(false);
            $table->string('duration_preset', 16)->default('eternal');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('inviter_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('co_producer_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['product_id', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_coproducers');
    }
};
