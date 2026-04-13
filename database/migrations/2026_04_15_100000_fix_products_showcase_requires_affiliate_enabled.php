<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }
        if (! Schema::hasColumn('products', 'affiliate_show_in_showcase') || ! Schema::hasColumn('products', 'affiliate_enabled')) {
            return;
        }

        DB::table('products')
            ->where('affiliate_show_in_showcase', true)
            ->where(function ($q): void {
                $q->where('affiliate_enabled', false)
                    ->orWhereNull('affiliate_enabled');
            })
            ->update(['affiliate_enabled' => true]);
    }

    public function down(): void
    {
        // Irreversível de forma segura (não desliga afiliação em massa).
    }
};
