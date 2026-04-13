<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_community_pages', function (Blueprint $table) {
            $table->string('icon', 50)->nullable()->after('title');
            $table->string('banner', 500)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('member_community_pages', function (Blueprint $table) {
            $table->dropColumn(['icon', 'banner']);
        });
    }
};
