<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_modules', function (Blueprint $table) {
            $table->boolean('show_title_on_cover')->default(true)->after('thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('member_modules', function (Blueprint $table) {
            $table->dropColumn('show_title_on_cover');
        });
    }
};
