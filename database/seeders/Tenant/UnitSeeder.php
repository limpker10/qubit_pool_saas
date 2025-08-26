<?php
// database/seeders/UnitSeeder.php
namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name'=>'Unidad','abbreviation'=>'UND'],
            ['name'=>'Caja','abbreviation'=>'CJ'],
            ['name'=>'Litro','abbreviation'=>'L'],
            ['name'=>'Botella','abbreviation'=>'BOT'],
        ];
        foreach ($data as $u) Unit::firstOrCreate(['name'=>$u['name']], $u);
    }
}
