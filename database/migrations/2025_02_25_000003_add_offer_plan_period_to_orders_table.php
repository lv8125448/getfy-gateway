<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_offer_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->after('product_offer_id')->constrained()->nullOnDelete();
            $table->date('period_start')->nullable()->after('subscription_plan_id');
            $table->date('period_end')->nullable()->after('period_start');
            $table->boolean('is_renewal')->default(false)->after('period_end');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_offer_id']);
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['product_offer_id', 'subscription_plan_id', 'period_start', 'period_end', 'is_renewal']);
        });
    }
};
