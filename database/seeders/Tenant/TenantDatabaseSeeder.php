<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Si alguno depende de otro, respeta ese orden:
        // - Estados de mesa, Unidades y Almacenes primero (catálogos base)
        // - Categorías y Productos después (usan catálogos base)
        // - Módulos y Planes al final (no dependen de los anteriores)
        DB::connection()->transaction(function () {
            $this->call([
                TableStatusSeeder::class,
                UnitSeeder::class,
                WarehouseSeeder::class,
                CategorySeeder::class,
                ProductSeeder::class,
            ]);
        });
    }
}
