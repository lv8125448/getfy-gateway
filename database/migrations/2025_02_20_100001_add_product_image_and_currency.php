<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image', 500)->nullable()->after('description');
            $table->string('currency', 8)->default('BRL')->after('price');
        });

        // Migrate legacy types: course -> area_membros, other -> link (ebook stays)
        \DB::table('products')->where('type', 'course')->update(['type' => 'area_membros']);
        \DB::table('products')->where('type', 'other')->update(['type' => 'link']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image', 'currency']);
        });
    }
};
