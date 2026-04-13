<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_area_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 32); // path, subdomain, custom
            $table->string('value')->nullable(); // slug for path/subdomain, full domain for custom
            $table->timestamps();
            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_area_domains');
    }
};
