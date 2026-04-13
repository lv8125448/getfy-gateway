<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Campanhas de e-mail marketing passam a ser globais (operador da plataforma).
        DB::table('email_campaigns')->whereNotNull('tenant_id')->update(['tenant_id' => null]);
    }

    public function down(): void
    {
        // Irreversível sem backup dos tenant_id originais.
    }
};
