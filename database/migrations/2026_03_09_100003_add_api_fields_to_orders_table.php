<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (! Schema::hasColumn('orders', 'api_application_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('api_application_id')->nullable()->after('subscription_plan_id')->constrained('api_applications')->nullOnDelete();
                $table->unsignedBigInteger('api_checkout_session_id')->nullable()->after('api_application_id')->index();
            });
        } elseif (! Schema::hasColumn('orders', 'api_checkout_session_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('api_checkout_session_id')->nullable()->after('api_application_id')->index();
            });
        }

        // Allow API-only orders without a product (payment processing only)
        $fkName = $this->getProductIdForeignKeyName();
        if ($fkName !== null) {
            Schema::table('orders', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }
        if ($this->isProductIdNullable() === false) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('product_id', 36)->nullable()->change();
            });
        }
        if ($this->getProductIdForeignKeyName() === null) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }
    }

    private function getProductIdForeignKeyName(): ?string
    {
        $schema = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products' LIMIT 1",
            [$schema]
        );

        return $row->CONSTRAINT_NAME ?? null;
    }

    private function isProductIdNullable(): bool
    {
        $schema = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'product_id' LIMIT 1",
            [$schema]
        );

        return $row && strtoupper($row->IS_NULLABLE) === 'YES';
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['api_application_id']);
            $table->dropColumn('api_checkout_session_id');
        });
        $fkName = $this->getProductIdForeignKeyName();
        if ($fkName !== null) {
            Schema::table('orders', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->string('product_id', 36)->nullable(false)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
