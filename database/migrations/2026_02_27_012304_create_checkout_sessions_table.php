<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('checkout_sessions')) {
            return;
        }
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_offer_id')->nullable();
            $table->unsignedBigInteger('subscription_plan_id')->nullable();
            $table->string('checkout_slug', 64);
            $table->string('session_token', 64)->unique();
            $table->string('step', 32)->default('visit');
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('customer_ip', 45)->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'step']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
