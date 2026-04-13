<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->json('checkout_config')->nullable()->after('checkout_slug');
        });
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('checkout_config')->nullable()->after('checkout_slug');
        });
    }

    public function down(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->dropColumn('checkout_config');
        });
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('checkout_config');
        });
    }
};
