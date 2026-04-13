<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('utmify_integration_product')) {
            Schema::create('utmify_integration_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('utmify_integration_id')->constrained('utmify_integrations')->cascadeOnDelete();
                $table->string('product_id', 36);
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->unique(['utmify_integration_id', 'product_id'], 'utmify_int_product_unique');
            });
        } else {
            $exists = DB::selectOne(
                "SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = 'utmify_integration_product' AND index_name = 'utmify_int_product_unique' LIMIT 1",
                [DB::getDatabaseName()]
            );
            if (! $exists) {
                Schema::table('utmify_integration_product', function (Blueprint $table) {
                    $table->unique(['utmify_integration_id', 'product_id'], 'utmify_int_product_unique');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utmify_integration_product');
    }
};
