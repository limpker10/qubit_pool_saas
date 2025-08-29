<?php
// app/Http/Controllers/Tenant/TableRentalItemController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Models\Tenant\TableRental;
use App\Models\Tenant\TableRentalItem;
use App\Models\Tenant\PoolTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableRentalItemController extends Controller
{
    public function index(TableRental $rental)
    {
        $rental->load(['itemsAll' => function ($q) {
            $q->orderBy('id', 'asc');
        }]);

        return response()->json([
            'rental' => $rental,
            'items'  => $rental->itemsAll, // incluye anulados
        ]);
    }

    public function store(Request $request, TableRental $rental)
    {
        $data = $request->validate([
            'product_id'   => 'nullable|exists:products,id',
            'product_name' => 'nullable|string|max:255',
            'unit_id'      => 'nullable|exists:units,id',
            'unit_name'    => 'nullable|string|max:100',
            'qty'          => 'required|numeric|min:0.0001',
            'unit_price'   => 'required|numeric|min:0',
            'discount'     => 'nullable|numeric|min:0',
            'observation'  => 'nullable|string',
            'client_op_id' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($rental, $data, $request) {
            // Bloquea el alquiler y la mesa
            $rental = TableRental::whereKey($rental->getKey())->lockForUpdate()->firstOrFail();
            if ($rental->status !== 'open') {
                return response()->json(['message' => 'El alquiler no está abierto'], 409);
            }

            // Snapshot de producto/unidad
            if (!empty($data['product_id'])) {
                $p = Product::find($data['product_id']);
                if ($p) {
                    $data['product_name'] = $data['product_name'] ?? $p->name;
                    $data['unit_id']      = $data['unit_id'] ?? $p->unit_id;
                    $data['unit_name']    = $data['unit_name'] ?? optional($p->unit)->name;
                }
            }

            $data['created_by_id'] = $request->user()->id ?? null;

            $item = new TableRentalItem($data);
            $item->table_rental_id = $rental->id;
            $item->status = 'ok';
            $item->save();

            $rental->recalcTotals(true);

            return response()->json([
                'rental' => $rental->fresh(),
                'item'   => $item,
            ], 201);
        });
    }

    public function storeBulk(Request $request, TableRental $rental)
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.unit_id'      => 'nullable|exists:units,id',
            'items.*.unit_name'    => 'nullable|string|max:100',
            'items.*.qty'          => 'required|numeric|min:0.0001',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.observation'  => 'nullable|string',
            'items.*.client_op_id' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($rental, $payload, $request) {
            $rental = TableRental::whereKey($rental->getKey())->lockForUpdate()->firstOrFail();
            if ($rental->status !== 'open') {
                return response()->json(['message' => 'El alquiler no está abierto'], 409);
            }

            $created = [];
            foreach ($payload['items'] as $row) {
                // Idempotencia simple (si la usas)
                if (!empty($row['client_op_id'])) {
                    $exists = TableRentalItem::where('table_rental_id', $rental->id)
                        ->where('client_op_id', $row['client_op_id'])
                        ->exists();
                    if ($exists) continue; // salta duplicado
                }

                // Snapshot producto/unidad
                if (!empty($row['product_id'])) {
                    $p = Product::find($row['product_id']);
                    if ($p) {
                        $row['product_name'] = $row['product_name'] ?? $p->name;
                        $row['unit_id']      = $row['unit_id'] ?? $p->unit_id;
                        $row['unit_name']    = $row['unit_name'] ?? optional($p->unit)->name;
                    }
                }

                $row['created_by_id'] = $request->user()->id ?? null;

                $itm = new TableRentalItem($row);
                $itm->table_rental_id = $rental->id;
                $itm->status = 'ok';
                $itm->save();
                $created[] = $itm;
            }

            $rental->recalcTotals(true);

            return response()->json([
                'rental' => $rental->fresh(),
                'items'  => $created,
            ], 201);
        });
    }

    public function update(Request $request, TableRentalItem $item)
    {
        return DB::transaction(function () use ($request, $item) {
            $rental = TableRental::whereKey($item->table_rental_id)->lockForUpdate()->firstOrFail();
            if ($rental->status !== 'open') {
                return response()->json(['message' => 'El alquiler no está abierto'], 409);
            }

            $data = $request->validate([
                'qty'         => 'nullable|numeric|min:0.0001',
                'unit_price'  => 'nullable|numeric|min:0',
                'discount'    => 'nullable|numeric|min:0',
                'observation' => 'nullable|string',
            ]);

            $item->fill($data);
            $item->save();

            $rental->recalcTotals(true);

            return response()->json([
                'rental' => $rental->fresh(),
                'item'   => $item,
            ]);
        });
    }

    public function destroy(TableRentalItem $item)
    {
        return DB::transaction(function () use ($item) {
            $rental = TableRental::whereKey($item->table_rental_id)->lockForUpdate()->firstOrFail();
            if ($rental->status !== 'open') {
                return response()->json(['message' => 'El alquiler no está abierto'], 409);
            }

            // Anular en lugar de borrar duro (mantener histórico)
            $item->status = 'voided';
            $item->save();

            $rental->recalcTotals(true);

            return response()->json([
                'rental' => $rental->fresh(),
                'item'   => $item,
            ]);
        });
    }
}
