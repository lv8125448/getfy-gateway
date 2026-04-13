<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cademi_integrations', function (Blueprint $table) {
            $table->string('delivery_method', 32)->default('postback_custom')->after('api_key');
            $table->text('postback_token')->nullable()->after('delivery_method');
        });
    }

    public function down(): void
    {
        Schema::table('cademi_integrations', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'postback_token']);
        });
    }
};

