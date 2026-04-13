<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_notifications', function (Blueprint $table) {
            $table->string('event_key', 128)->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('panel_notifications', function (Blueprint $table) {
            $table->dropColumn('event_key');
        });
    }
};
