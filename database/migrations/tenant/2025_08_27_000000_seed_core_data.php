<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        // =========================
        // 1) CATEGORÍAS
        // =========================
        $categories = ['Bebidas','Snacks','Accesorios','Mantenimiento','Alquiler de Mesa'];
        foreach ($categories as $name) {
            DB::table('categories')->updateOrInsert(
                ['name' => $name],
                ['name' => $name, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // =========================
        // 2) ESTADOS DE MESA
        // =========================
        $statuses = ['available','in_progress','paused','cancelled','maintenance'];
        foreach ($statuses as $status) {
            DB::table('table_statuses')->updateOrInsert(
                ['name' => $status],
                ['name' => $status, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // =========================
        // 3) UNIDADES
        // =========================
        $units = [
            ['name'=>'Unidad','abbreviation'=>'UND'],
            ['name'=>'Caja','abbreviation'=>'CJ'],
            ['name'=>'Litro','abbreviation'=>'L'],
            ['name'=>'Botella','abbreviation'=>'BOT'],
        ];
        foreach ($units as $u) {
            DB::table('units')->updateOrInsert(
                ['name' => $u['name']],
                ['name' => $u['name'], 'abbreviation' => $u['abbreviation'], 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // =========================
        // 4) ALMACENES
        // =========================
        DB::table('warehouses')->updateOrInsert(
            ['code' => 'PRINC'],
            ['code'=>'PRINC','name'=>'Principal','address'=>'Local central','created_at'=>$now,'updated_at'=>$now]
        );
        DB::table('warehouses')->updateOrInsert(
            ['code' => 'BAR'],
            ['code'=>'BAR','name'=>'Bar','address'=>'Barra','created_at'=>$now,'updated_at'=>$now]
        );

        // =========================
        // 5) PRODUCTOS DE EJEMPLO + STOCK (solo si no hay productos)
        // =========================
        if (DB::table('products')->count() === 0) {
            // Garantizar prerequisitos
            $categoryId = DB::table('categories')->inRandomOrder()->value('id');
            if (!$categoryId) {
                $categoryId = DB::table('categories')->insertGetId([
                    'name' => 'General', 'created_at' => $now, 'updated_at' => $now
                ]);
            }

            $unitId = DB::table('units')->inRandomOrder()->value('id');
            if (!$unitId) {
                $unitId = DB::table('units')->insertGetId([
                    'name' => 'Unidad', 'abbreviation' => 'UND',
                    'created_at' => $now, 'updated_at' => $now
                ]);
            }

            $whId = DB::table('warehouses')->where('code', 'PRINC')->value('id');
            if (!$whId) {
                $whId = DB::table('warehouses')->insertGetId([
                    'code' => 'PRINC', 'name' => 'Principal', 'address' => 'Local central',
                    'created_at' => $now, 'updated_at' => $now
                ]);
            }

            // Insertar 30 productos deterministas "MIG-*"
            for ($i = 1; $i <= 30; $i++) {
                $sku  = sprintf('MIG-%04d', $i);
                $name = sprintf('Producto MIG %02d', $i);
                $cost = round(1 + fmod($i * 1.37, 50), 2);   // ~1.00 - 50.00
                $sale = round($cost * 1.4 + 0.99, 2);        // margen simple

                $productId = DB::table('products')->insertGetId([
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => 'MIG' . str_pad((string)$i, 9, '0', STR_PAD_LEFT),
                    'description' => 'Producto de ejemplo insertado por migración.',
                    'brand' => 'Demo',
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'default_cost_price' => $cost,
                    'default_sale_price' => $sale,
                    'min_stock' => ($i % 7),
                    'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('product_stocks')->updateOrInsert(
                    ['product_id' => $productId, 'warehouse_id' => $whId],
                    [
                        'product_id' => $productId,
                        'warehouse_id' => $whId,
                        'quantity' => ($i % 16) + 5, // 5..20
                        'avg_unit_cost' => $cost,
                        'created_at' => $now, 'updated_at' => $now
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // 1) Borrar SOLO productos creados por esta migración
        $productIds = DB::table('products')->where('sku', 'like', 'MIG-%')->pluck('id');
        if ($productIds->isNotEmpty()) {
            DB::table('product_stocks')->whereIn('product_id', $productIds)->delete();
            DB::table('products')->whereIn('id', $productIds)->delete();
        }

        // 2) Intentar borrar almacenes PRIN/BAR solo si están sin dependencias
        foreach (['PRINC','BAR'] as $code) {
            $wid = DB::table('warehouses')->where('code', $code)->value('id');
            if ($wid) {
                $hasStocks = DB::table('product_stocks')->where('warehouse_id', $wid)->exists();
                if (!$hasStocks) {
                    DB::table('warehouses')->where('id', $wid)->delete();
                }
            }
        }

        // 3) Borrar unidades, categorías y estados insertados
        DB::table('units')->whereIn('name', ['Unidad','Caja','Litro','Botella'])->delete();
        DB::table('categories')->whereIn('name', ['Bebidas','Snacks','Accesorios','Mantenimiento','Alquiler de Mesa'])->delete();
        DB::table('table_statuses')->whereIn('name', ['available','in_progress','paused','cancelled','maintenance'])->delete();
    }
};
