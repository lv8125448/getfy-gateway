<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_method', 32)->nullable()->after('gateway_id');
            });
        }

        if (Schema::hasTable('tenant_wallets')) {
            if (! Schema::hasColumn('tenant_wallets', 'available_pix')) {
                Schema::table('tenant_wallets', function (Blueprint $table) {
                    $table->decimal('available_pix', 14, 2)->default(0)->after('available_balance');
                    $table->decimal('available_card', 14, 2)->default(0)->after('available_pix');
                    $table->decimal('available_boleto', 14, 2)->default(0)->after('available_card');
                    $table->decimal('pending_pix', 14, 2)->default(0)->after('pending_balance');
                    $table->decimal('pending_card', 14, 2)->default(0)->after('pending_pix');
                    $table->decimal('pending_boleto', 14, 2)->default(0)->after('pending_card');
                });
                if (Schema::hasColumn('tenant_wallets', 'available_balance')) {
                    DB::table('tenant_wallets')->orderBy('id')->chunk(100, function ($rows) {
                        foreach ($rows as $row) {
                            $avail = (float) ($row->available_balance ?? 0);
                            $pend = (float) ($row->pending_balance ?? 0);
                            DB::table('tenant_wallets')->where('id', $row->id)->update([
                                'available_pix' => $avail,
                                'pending_pix' => $pend,
                            ]);
                        }
                    });
                }
            }
        }

        if (! Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->unsignedBigInteger('withdrawal_id')->nullable()->index();
                $table->string('bucket', 16);
                $table->string('type', 32);
                $table->decimal('amount_gross', 14, 2)->default(0);
                $table->decimal('amount_fee', 14, 2)->default(0);
                $table->decimal('amount_net', 14, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('withdrawals')) {
            if (! Schema::hasColumn('withdrawals', 'fee_amount')) {
                Schema::table('withdrawals', function (Blueprint $table) {
                    $table->decimal('fee_amount', 14, 2)->default(0)->after('amount');
                    $table->decimal('net_amount', 14, 2)->default(0)->after('fee_amount');
                    $table->string('bucket', 16)->default('pix');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            Schema::dropIfExists('wallet_transactions');
        }
        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if (Schema::hasColumn('withdrawals', 'fee_amount')) {
                    $table->dropColumn(['fee_amount', 'net_amount', 'bucket']);
                }
            });
        }
        if (Schema::hasTable('tenant_wallets')) {
            Schema::table('tenant_wallets', function (Blueprint $table) {
                foreach (['available_pix', 'available_card', 'available_boleto', 'pending_pix', 'pending_card', 'pending_boleto'] as $col) {
                    if (Schema::hasColumn('tenant_wallets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};
