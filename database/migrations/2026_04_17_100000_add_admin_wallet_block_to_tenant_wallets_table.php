<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_wallets')) {
            return;
        }

        Schema::table('tenant_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_wallets', 'admin_withdrawal_blocked')) {
                $table->boolean('admin_withdrawal_blocked')->default(false);
            }
            if (! Schema::hasColumn('tenant_wallets', 'admin_blocked_amount')) {
                $table->decimal('admin_blocked_amount', 14, 2)->nullable();
            }
            if (! Schema::hasColumn('tenant_wallets', 'admin_block_until')) {
                $table->timestamp('admin_block_until')->nullable();
            }
            if (! Schema::hasColumn('tenant_wallets', 'admin_block_note')) {
                $table->string('admin_block_note', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_wallets')) {
            return;
        }

        Schema::table('tenant_wallets', function (Blueprint $table) {
            foreach (['admin_block_note', 'admin_block_until', 'admin_blocked_amount', 'admin_withdrawal_blocked'] as $col) {
                if (Schema::hasColumn('tenant_wallets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
