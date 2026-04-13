<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL: products.id é UUID/char(36), mas checkout_sessions foi criada com product_id bigint.
 * Corrige coluna e FK (sessões são efémeras — trunca antes de alterar tipo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('checkout_sessions') || ! Schema::hasColumn('checkout_sessions', 'product_id')) {
            return;
        }

        DB::transaction(function (): void {
            $this->runPgsql();
        });
    }

    private function runPgsql(): void
    {
        $productId = DB::selectOne(
            "SELECT data_type, character_maximum_length FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = 'products' AND column_name = 'id'"
        );
        $sessionProductId = DB::selectOne(
            "SELECT data_type, character_maximum_length FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = 'checkout_sessions' AND column_name = 'product_id'"
        );

        if (! $productId || ! $sessionProductId) {
            return;
        }

        $productType = strtolower((string) $productId->data_type);
        $sessionType = strtolower((string) $sessionProductId->data_type);

        $productsIsUuidLike = $productType === 'uuid'
            || (in_array($productType, ['character', 'character varying'], true) && (int) ($productId->character_maximum_length ?? 0) === 36);

        if (! $productsIsUuidLike) {
            return;
        }

        if (in_array($sessionType, ['character', 'character varying'], true) && (int) ($sessionProductId->character_maximum_length ?? 0) === 36) {
            return;
        }

        if (! in_array($sessionType, ['bigint', 'integer', 'smallint'], true)) {
            return;
        }

        $fkRows = DB::select(
            "SELECT tc.constraint_name
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON tc.constraint_schema = kcu.constraint_schema
              AND tc.constraint_name = kcu.constraint_name
             WHERE tc.table_schema = current_schema()
               AND tc.table_name = 'checkout_sessions'
               AND tc.constraint_type = 'FOREIGN KEY'
               AND kcu.column_name = 'product_id'"
        );
        foreach ($fkRows as $row) {
            $name = $row->constraint_name ?? null;
            if ($name) {
                DB::statement('ALTER TABLE checkout_sessions DROP CONSTRAINT IF EXISTS "'.str_replace('"', '""', $name).'"');
            }
        }

        DB::statement('TRUNCATE TABLE checkout_sessions RESTART IDENTITY CASCADE');

        DB::statement('ALTER TABLE checkout_sessions DROP COLUMN product_id');

        $useNativeUuid = $productType === 'uuid';
        Schema::table('checkout_sessions', function (Blueprint $table) use ($useNativeUuid) {
            if ($useNativeUuid) {
                $table->uuid('product_id');
            } else {
                $table->char('product_id', 36);
            }
        });
        DB::statement('ALTER TABLE checkout_sessions ALTER COLUMN product_id SET NOT NULL');

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Reversão arriscada com UUID em products — omitida.
    }
};
