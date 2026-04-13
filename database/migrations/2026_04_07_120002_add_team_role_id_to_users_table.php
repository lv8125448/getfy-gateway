<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'team_role_id')) {
                $table->foreignId('team_role_id')->nullable()->after('tenant_id')->constrained('team_roles')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_role_id')) {
                $table->dropForeign(['team_role_id']);
                $table->dropColumn('team_role_id');
            }
        });
    }
};

