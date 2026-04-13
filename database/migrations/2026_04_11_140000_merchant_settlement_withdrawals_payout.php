<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sem ->after(): compatível com PostgreSQL (after só MySQL).
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'merchant_settlement_overrides')) {
                    $table->json('merchant_settlement_overrides')->nullable();
                }
                if (! Schema::hasColumn('users', 'merchant_gateway_order')) {
                    $table->json('merchant_gateway_order')->nullable();
                }
                if (! Schema::hasColumn('users', 'payout_settings')) {
                    $table->json('payout_settings')->nullable();
                }
            });
        }

        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if (! Schema::hasColumn('withdrawals', 'payout_provider')) {
                    $table->string('payout_provider', 32)->nullable();
                }
                if (! Schema::hasColumn('withdrawals', 'payout_external_id')) {
                    $table->string('payout_external_id', 80)->nullable()->index();
                }
                if (! Schema::hasColumn('withdrawals', 'payout_meta')) {
                    $table->json('payout_meta')->nullable();
                }
                if (! Schema::hasColumn('withdrawals', 'payout_manual')) {
                    $table->boolean('payout_manual')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                foreach (['payout_manual', 'payout_meta', 'payout_external_id', 'payout_provider'] as $col) {
                    if (Schema::hasColumn('withdrawals', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['payout_settings', 'merchant_gateway_order', 'merchant_settlement_overrides'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
