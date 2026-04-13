<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        // Migrar cupons que já têm product_id para a pivot
        DB::table('coupons')->whereNotNull('product_id')->orderBy('id')->chunk(100, function ($coupons) {
            foreach ($coupons as $c) {
                DB::table('coupon_product')->insertOrIgnore([
                    'coupon_id' => $c->id,
                    'product_id' => $c->product_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product');
    }
};
