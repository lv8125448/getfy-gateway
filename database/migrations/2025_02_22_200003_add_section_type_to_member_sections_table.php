<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_sections', function (Blueprint $table) {
            $table->string('section_type', 30)->default('courses')->after('cover_mode');
        });
    }

    public function down(): void
    {
        Schema::table('member_sections', function (Blueprint $table) {
            $table->dropColumn('section_type');
        });
    }
};
