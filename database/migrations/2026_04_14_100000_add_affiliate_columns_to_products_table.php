<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('affiliate_enabled')->default(false)->after('is_active');
            $table->decimal('affiliate_commission_percent', 8, 2)->default(0)->after('affiliate_enabled');
            $table->boolean('affiliate_manual_approval')->default(true)->after('affiliate_commission_percent');
            $table->boolean('affiliate_show_in_showcase')->default(false)->after('affiliate_manual_approval');
            $table->string('affiliate_page_url', 2048)->nullable()->after('affiliate_show_in_showcase');
            $table->string('affiliate_support_email', 255)->nullable()->after('affiliate_page_url');
            $table->text('affiliate_showcase_description')->nullable()->after('affiliate_support_email');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'affiliate_enabled',
                'affiliate_commission_percent',
                'affiliate_manual_approval',
                'affiliate_show_in_showcase',
                'affiliate_page_url',
                'affiliate_support_email',
                'affiliate_showcase_description',
            ]);
        });
    }
};
