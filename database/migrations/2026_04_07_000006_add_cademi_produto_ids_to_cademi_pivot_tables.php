<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cademi_integration_product', function (Blueprint $table) {
            $table->text('cademi_produto_ids')->nullable()->after('cademi_produto_id');
        });

        Schema::table('cademi_integration_product_offer', function (Blueprint $table) {
            $table->text('cademi_produto_ids')->nullable()->after('cademi_produto_id');
        });

        Schema::table('cademi_integration_subscription_plan', function (Blueprint $table) {
            $table->text('cademi_produto_ids')->nullable()->after('cademi_produto_id');
        });
    }

    public function down(): void
    {
        Schema::table('cademi_integration_product', function (Blueprint $table) {
            $table->dropColumn('cademi_produto_ids');
        });

        Schema::table('cademi_integration_product_offer', function (Blueprint $table) {
            $table->dropColumn('cademi_produto_ids');
        });

        Schema::table('cademi_integration_subscription_plan', function (Blueprint $table) {
            $table->dropColumn('cademi_produto_ids');
        });
    }
};

