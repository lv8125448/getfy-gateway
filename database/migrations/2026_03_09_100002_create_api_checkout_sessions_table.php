<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_application_id')->constrained('api_applications')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('session_token', 64)->unique();
            $table->json('customer');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('product_id', 36)->nullable();
            $table->unsignedBigInteger('product_offer_id')->nullable();
            $table->unsignedBigInteger('subscription_plan_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('return_url', 512)->nullable();
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();

            $table->index(['api_application_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_checkout_sessions');
    }
};
