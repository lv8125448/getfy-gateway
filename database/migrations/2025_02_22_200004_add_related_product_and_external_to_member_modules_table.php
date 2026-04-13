<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_modules', function (Blueprint $table) {
            $table->foreignId('related_product_id')->nullable()->after('product_id')->constrained('products')->nullOnDelete();
            $table->string('access_type', 20)->nullable()->after('related_product_id');
            $table->string('external_url', 500)->nullable()->after('access_type');
        });
    }

    public function down(): void
    {
        Schema::table('member_modules', function (Blueprint $table) {
            $table->dropForeign(['related_product_id']);
            $table->dropColumn(['access_type', 'external_url']);
        });
    }
};
