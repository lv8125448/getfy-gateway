<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kyc_documents')) {
            return;
        }

        if (! Schema::hasColumn('kyc_documents', 'public_token')) {
            Schema::table('kyc_documents', function (Blueprint $table) {
                $table->string('public_token', 36)->nullable()->unique()->after('id');
            });
        }

        foreach (DB::table('kyc_documents')->whereNull('public_token')->cursor() as $row) {
            DB::table('kyc_documents')->where('id', $row->id)->update(['public_token' => (string) Str::uuid()]);
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE kyc_documents MODIFY public_token VARCHAR(36) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kyc_documents ALTER COLUMN public_token SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kyc_documents')) {
            return;
        }

        Schema::table('kyc_documents', function (Blueprint $table) {
            if (Schema::hasColumn('kyc_documents', 'public_token')) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            }
        });
    }
};
