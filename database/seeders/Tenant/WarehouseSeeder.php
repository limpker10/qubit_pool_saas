<?php
// database/seeders/WarehouseSeeder.php
namespace Database\Seeders\Tenant;


use Illuminate\Database\Seeder;
use App\Models\Tenant\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(['code'=>'PRINC'], ['name'=>'Principal','address'=>'Local central']);
        Warehouse::firstOrCreate(['code'=>'BAR'], ['name'=>'Bar','address'=>'Barra']);
    }
}
