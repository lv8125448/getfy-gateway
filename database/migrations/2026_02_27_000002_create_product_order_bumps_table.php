<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_order_bumps', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 36);
            $table->string('target_product_id', 36);
            $table->unsignedBigInteger('target_product_offer_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->string('cta_title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('target_product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('target_product_offer_id')->references('id')->on('product_offers')->nullOnDelete();
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_bumps');
    }
};
