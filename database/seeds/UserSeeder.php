<?php

namespace Database\Seeders;

use App\Models\User;
use Framework\Database\Seeder;

/** Dados de demonstração de usuários para desenvolvimento local. */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = new User();

        $users->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Administrador',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role'     => 'admin',
                'active'   => 1,
            ]
        );

        $users->firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'     => 'Usuário Demo',
                'password' => password_hash('user123', PASSWORD_DEFAULT),
                'role'     => 'member',
                'active'   => 1,
            ]
        );
    }
}
