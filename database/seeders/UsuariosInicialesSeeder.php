<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsuariosInicialesSeeder extends Seeder
{
    /**
     * Crea un usuario por rol si no existe. La contraseña inicial se toma de SEED_USUARIOS_PASSWORD
     * y no se sobrescribe en usuarios ya existentes.
     */
    public function run(): void
    {
        $password = env('SEED_USUARIOS_PASSWORD');

        if (blank($password)) {
            $this->command?->warn('SEED_USUARIOS_PASSWORD no está definido en .env: no se crearon usuarios iniciales.');

            return;
        }

        $usuarios = [
            ['email' => 'admin@sistema.local', 'name' => 'Administrador', 'rol' => 'Administrador'],
            ['email' => 'gerente@sistema.local', 'name' => 'Gerente', 'rol' => 'Gerente'],
        ];

        foreach ($usuarios as $datos) {
            $usuario = User::firstOrCreate(
                ['email' => $datos['email']],
                ['name' => $datos['name'], 'password' => $password, 'email_verified_at' => now()],
            );

            $usuario->assignRole($datos['rol']);
        }
    }
}
