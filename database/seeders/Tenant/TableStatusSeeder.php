<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TableStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'available',
            'in_progress',
            'paused',
            'cancelled',
            'maintenance',
        ];

        foreach ($statuses as $status) {
            DB::table('table_statuses')->insert([
                'name' => $status,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
