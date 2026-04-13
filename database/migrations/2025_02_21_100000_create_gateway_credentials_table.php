<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('gateway_slug', 64)->index();
            $table->text('credentials')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'gateway_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_credentials');
    }
};
