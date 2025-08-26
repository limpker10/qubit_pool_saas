<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder {
    public function run() {
        DB::table('modules')->insert(collect([
            ['key'=>'dashboard','name'=>'Dashboard'],
            ['key'=>'ventas','name'=>'Ventas'],
            ['key'=>'compras','name'=>'Compras'],
            ['key'=>'clientes','name'=>'Clientes'],
            ['key'=>'inventario','name'=>'Inventario'],
        ])->map(fn($m)=> $m + ['is_active'=>true,'created_at'=>now(),'updated_at'=>now()])->all());
    }
}
