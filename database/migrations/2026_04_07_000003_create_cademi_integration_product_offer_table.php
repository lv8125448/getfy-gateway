<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cademi_integration_product_offer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cademi_integration_id')->constrained('cademi_integrations')->cascadeOnDelete();
            $table->foreignId('product_offer_id')->constrained('product_offers')->cascadeOnDelete();
            $table->unsignedBigInteger('cademi_tag_id')->nullable();
            $table->unsignedBigInteger('cademi_produto_id')->nullable();
            $table->timestamps();

            $table->unique(['cademi_integration_id', 'product_offer_id'], 'cademi_int_offer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cademi_integration_product_offer');
    }
};

