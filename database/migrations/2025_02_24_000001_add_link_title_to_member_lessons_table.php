<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            $table->string('link_title', 255)->nullable()->after('content_url');
        });
    }

    public function down(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            $table->dropColumn('link_title');
        });
    }
};
