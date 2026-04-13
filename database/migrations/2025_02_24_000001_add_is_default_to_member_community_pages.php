<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_community_pages', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_public_posting');
        });
    }

    public function down(): void
    {
        Schema::table('member_community_pages', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
