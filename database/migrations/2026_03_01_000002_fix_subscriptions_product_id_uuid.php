<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix subscriptions.product_id when products.id is UUID (char 36)
 * but subscriptions still has bigInteger. Run when you see "Data truncated for column 'product_id'"
 * on subscriptions insert (e.g. checkout with plan, Subscription::create).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'product_id')) {
            return;
        }

        $db = DB::getDatabaseName();

        $productIdType = DB::selectOne(
            "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'products' AND COLUMN_NAME = 'id'",
            [$db]
        );
        if (! $productIdType || $productIdType->DATA_TYPE !== 'char' || (int) $productIdType->CHARACTER_MAXIMUM_LENGTH !== 36) {
            return;
        }

        $col = DB::selectOne(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'product_id'",
            [$db]
        );
        if (! $col || $col->DATA_TYPE === 'char') {
            return;
        }

        $fkName = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products' LIMIT 1",
            [$db]
        );
        if ($fkName && $fkName->CONSTRAINT_NAME) {
            DB::statement("ALTER TABLE `subscriptions` DROP FOREIGN KEY `{$fkName->CONSTRAINT_NAME}`");
        }

        DB::statement('ALTER TABLE `subscriptions` MODIFY `product_id` CHAR(36) NOT NULL');

        DB::table('subscriptions')->whereNotIn('product_id', DB::table('products')->select('id'))->delete();

        Schema::table('subscriptions', function (Blueprint $t) {
            $t->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Irreversible once products use UUID.
    }
};
