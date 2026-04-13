<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_applications', function (Blueprint $table) {
            $table->string('checkout_sidebar_bg', 32)->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('api_applications', function (Blueprint $table) {
            $table->dropColumn('checkout_sidebar_bg');
        });
    }
};
