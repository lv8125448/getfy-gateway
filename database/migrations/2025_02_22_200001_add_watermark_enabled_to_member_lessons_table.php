<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            $table->boolean('watermark_enabled')->default(false)->after('is_free');
        });
    }

    public function down(): void
    {
        Schema::table('member_lessons', function (Blueprint $table) {
            $table->dropColumn('watermark_enabled');
        });
    }
};
