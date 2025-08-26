<?php
// database/seeders/PlanSeeder.php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder {
    public function run() {
        DB::table('plans')->insert([
            ['code'=>'BASICO','name'=>'Básico','is_active'=>true, 'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'PRO','name'=>'Pro','is_active'=>true, 'created_at'=>now(),'updated_at'=>now()],
            ['code'=>'PREMIUM','name'=>'Premium','is_active'=>true, 'created_at'=>now(),'updated_at'=>now()],
        ]);
    }
}
