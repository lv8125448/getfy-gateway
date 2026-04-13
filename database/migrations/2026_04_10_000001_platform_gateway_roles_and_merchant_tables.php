<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'person_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('person_type', 16)->nullable()->after('team_role_id');
            });
        }
        if (! Schema::hasColumn('users', 'document')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('document', 32)->nullable()->after('person_type');
            });
        }
        if (! Schema::hasColumn('users', 'account_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('account_status', 32)->default('approved')->after('document');
            });
        }
        if (! Schema::hasColumn('users', 'merchant_fees')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('merchant_fees')->nullable()->after('account_status');
            });
        }

        // Antigo papel admin: vira infoprodutor com tenant_id = próprio id (quando não existe).
        DB::table('users')->where('role', 'admin')->update(['role' => 'infoprodutor']);
        DB::statement('UPDATE users SET tenant_id = id WHERE role = ? AND tenant_id IS NULL', ['infoprodutor']);

        if (! Schema::hasTable('tenant_wallets')) {
            Schema::create('tenant_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->unique()->index();
                $table->decimal('available_balance', 14, 2)->default(0);
                $table->decimal('pending_balance', 14, 2)->default(0);
                $table->string('currency', 8)->default('BRL');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('withdrawals')) {
            Schema::create('withdrawals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('status', 32)->default('pending')->index();
                $table->text('notes')->nullable();
                $table->string('currency', 8)->default('BRL');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_audit_logs')) {
            Schema::create('platform_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 128)->index();
                $table->json('metadata')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('tenant_wallets');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['person_type', 'document', 'account_status', 'merchant_fees']);
        });
    }
};
