<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spedy_integration_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spedy_integration_id')->constrained('spedy_integrations')->cascadeOnDelete();
            $table->string('product_id', 36);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['spedy_integration_id', 'product_id'], 'spedy_int_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spedy_integration_product');
    }
};
