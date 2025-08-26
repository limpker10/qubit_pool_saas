<?php
// database/seeders/CategorySeeder.php
namespace Database\Seeders\Tenant;

use App\Models\Tenant\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Bebidas','Snacks','Accesorios','Mantenimiento','Alquiler de Mesa'];
        foreach ($names as $n) Category::firstOrCreate(['name'=>$n]);
    }
}
