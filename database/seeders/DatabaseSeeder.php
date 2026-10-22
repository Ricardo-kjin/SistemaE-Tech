<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.default') === 'cambios') {
            throw new \RuntimeException('Los seeders de autenticación no deben ejecutarse contra la conexión CAMBIOS.');
        }

        $this->call([
            RolesPermisosSeeder::class,
            UsuariosInicialesSeeder::class,
        ]);
    }
}
