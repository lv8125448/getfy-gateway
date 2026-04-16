<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'refund_policy_days')) {
            return;
        }
        DB::table('products')->whereNull('refund_policy_days')->update(['refund_policy_days' => 7]);
    }

    public function down(): void
    {
        // irreversível: não limpamos valores 7 introduzidos por backfill
    }
};
