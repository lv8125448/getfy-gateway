<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_role_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_role_id')->constrained('team_roles')->cascadeOnDelete();
            $table->char('product_id', 36);
            $table->timestamps();

            $table->unique(['team_role_id', 'product_id']);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_role_product');
    }
};

