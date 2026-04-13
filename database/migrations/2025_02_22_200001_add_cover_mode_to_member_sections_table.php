<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_sections', function (Blueprint $table) {
            $table->string('cover_mode', 20)->default('vertical')->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('member_sections', function (Blueprint $table) {
            $table->dropColumn('cover_mode');
        });
    }
};
