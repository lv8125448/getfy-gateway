<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante tabelas do painel da plataforma quando a migração anterior falhou a meio
 * ou não foi executada (ex.: tenant_wallets em falta no PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
