<?php
// database/seeders/ProductSeeder.php
namespace Database\Seeders\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductStock;
use App\Models\Tenant\Warehouse;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::factory(30)->create()->each(function ($p) {
            // Crear stock inicial en almacén principal
            $wh = Warehouse::firstOrCreate(['code'=>'PRINC'], ['name'=>'Principal']);
            ProductStock::firstOrCreate([
                'product_id' => $p->id,
                'warehouse_id' => $wh->id,
            ], [
                'quantity' => rand(5,20),
                'avg_unit_cost' => $p->default_cost_price,
            ]);
        });
    }
}
