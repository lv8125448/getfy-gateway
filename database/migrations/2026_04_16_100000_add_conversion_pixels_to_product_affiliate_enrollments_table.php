<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments')) {
            return;
        }
        Schema::table('product_affiliate_enrollments', function (Blueprint $table) {
            $table->json('conversion_pixels')->nullable()->after('public_ref');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_affiliate_enrollments')) {
            return;
        }
        if (Schema::hasColumn('product_affiliate_enrollments', 'conversion_pixels')) {
            Schema::table('product_affiliate_enrollments', function (Blueprint $table) {
                $table->dropColumn('conversion_pixels');
            });
        }
    }
};
