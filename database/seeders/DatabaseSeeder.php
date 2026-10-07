<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Los roles y permisos se crean primero porque UserSeeder asigna
            // un rol a cada cuenta de prueba.
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
