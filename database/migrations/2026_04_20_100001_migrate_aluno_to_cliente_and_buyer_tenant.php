<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'aluno')->update(['role' => 'cliente']);
        // Compradores: identidade global (não é tenant da loja)
        DB::table('users')->where('role', 'cliente')->update(['tenant_id' => null]);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'cliente')->update(['role' => 'aluno']);
    }
};
