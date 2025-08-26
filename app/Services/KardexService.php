<?php
// app/Services/KardexService.php
namespace App\Services;

use App\Models\{KardexEntry, Product, ProductStock, Warehouse};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KardexService
{
    /**
     * Registra un movimiento y actualiza stock/costo promedio (por almacén).
     * $data: product_id, warehouse_id, movement('entrada'|'salida'|'ajuste'|'transfer_in'|'transfer_out'),
     *        quantity (int, positivo), unit_cost (solo requerido para entradas/ajustes positivos), meta opcional
     */
    public function record(array $data): KardexEntry
    {
        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            $qty = (int)($data['quantity'] ?? 0);
            if ($qty <= 0) throw new InvalidArgumentException('quantity debe ser > 0');

            $movement = $data['movement'];
            $stock = ProductStock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => 0, 'avg_unit_cost' => $product->default_cost_price]
            );

            $quantity_in = 0; $quantity_out = 0; $unit_cost = (float)($data['unit_cost'] ?? 0);

            if (in_array($movement, ['entrada','transfer_in'])) {
                if ($unit_cost <= 0) $unit_cost = (float)$product->default_cost_price;
                $quantity_in = $qty;
                // nuevo costo promedio: (stock_valor + ingreso_valor) / (stock_qty + ingreso_qty)
                $current_value = $stock->quantity * (float)$stock->avg_unit_cost;
                $in_value = $qty * $unit_cost;
                $new_qty = $stock->quantity + $qty;
                $new_avg = $new_qty > 0 ? ($current_value + $in_value) / $new_qty : 0;
                $stock->quantity = $new_qty;
                $stock->avg_unit_cost = $new_avg;
                $stock->save();
            } elseif (in_array($movement, ['salida','transfer_out'])) {
                if ($stock->quantity < $qty) throw new InvalidArgumentException('Stock insuficiente');
                $quantity_out = $qty;
                $unit_cost = (float)$stock->avg_unit_cost; // costo promedio vigente
                $stock->quantity -= $qty;
                $stock->save();
            } elseif ($movement === 'ajuste') {
                // Ajuste positivo o negativo según sign(quantity)
                // Aquí quantity siempre >0; usa flag direction
                $direction = $data['direction'] ?? 'positivo';
                if ($direction === 'positivo') {
                    if ($unit_cost <= 0) $unit_cost = (float)$product->default_cost_price;
                    $quantity_in = $qty;
                    $current_value = $stock->quantity * (float)$stock->avg_unit_cost;
                    $in_value = $qty * $unit_cost;
                    $new_qty = $stock->quantity + $qty;
                    $new_avg = $new_qty > 0 ? ($current_value + $in_value) / $new_qty : 0;
                    $stock->quantity = $new_qty;
                    $stock->avg_unit_cost = $new_avg;
                    $stock->save();
                } else {
                    if ($stock->quantity < $qty) throw new InvalidArgumentException('Stock insuficiente');
                    $quantity_out = $qty;
                    $unit_cost = (float)$stock->avg_unit_cost;
                    $stock->quantity -= $qty;
                    $stock->save();
                }
            } else {
                throw new InvalidArgumentException('movement inválido');
            }

            $total_cost = ($quantity_in > 0 ? $quantity_in * $unit_cost : $quantity_out * $unit_cost);

            // Guardar kardex con saldos post-movimiento
            return KardexEntry::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'movement' => $movement,
                'quantity_in' => $quantity_in,
                'quantity_out' => $quantity_out,
                'unit_cost' => $unit_cost,
                'total_cost' => $total_cost,
                'balance_qty' => $stock->quantity,
                'balance_avg_unit_cost' => $stock->avg_unit_cost,
                'balance_total_cost' => $stock->quantity * $stock->avg_unit_cost,
                'document_type' => $data['document_type'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'movement_date' => $data['movement_date'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
