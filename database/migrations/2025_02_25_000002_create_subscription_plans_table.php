<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('currency', 8)->nullable();
            $table->string('interval', 32); // weekly, monthly, quarterly, semi_annual, annual, lifetime
            $table->string('checkout_slug', 16)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->string('gateway_plan_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
