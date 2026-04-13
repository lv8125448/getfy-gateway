<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_webhook');

        Schema::create('product_webhook', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 36);
            $table->unsignedBigInteger('webhook_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('webhook_id')->references('id')->on('webhooks')->onDelete('cascade');

            $table->unique(['product_id', 'webhook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_webhook');
    }
};
