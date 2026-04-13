<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updated = DB::table('users')
            ->where('email', 'dev@dev.com')
            ->update(['role' => User::ROLE_ADMIN]);

        if ($updated === 0) {
            // Não falha a migração se o email não existir (outro ambiente).
        }
    }

    public function down(): void
    {
        // Opcional: reverter não é seguro sem saber o role anterior.
    }
};
