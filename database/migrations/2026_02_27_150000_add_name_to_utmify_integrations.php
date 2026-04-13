<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utmify_integrations', function (Blueprint $table) {
            $table->string('name', 255)->default('UTMfy')->after('tenant_id');
            $table->dropUnique(['tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('utmify_integrations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->unique('tenant_id');
            $table->dropColumn('name');
        });
    }
};
