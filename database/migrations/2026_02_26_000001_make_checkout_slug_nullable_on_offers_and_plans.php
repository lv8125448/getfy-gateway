<?php

use Illuminate\Database\Migrations\Migration;
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

        DB::statement('ALTER TABLE product_offers MODIFY checkout_slug VARCHAR(16) NULL');
        DB::statement('ALTER TABLE subscription_plans MODIFY checkout_slug VARCHAR(16) NULL');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        DB::statement('ALTER TABLE product_offers MODIFY checkout_slug VARCHAR(16) NOT NULL');
        DB::statement('ALTER TABLE subscription_plans MODIFY checkout_slug VARCHAR(16) NOT NULL');
    }
};
