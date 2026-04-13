<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'infoprodutor@example.com'],
            [
                'name' => 'Infoprodutor',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'tenant_id' => null,
            ]
        );
    }
}
