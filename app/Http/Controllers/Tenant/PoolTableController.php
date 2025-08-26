<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\CashMovement;
use App\Models\Tenant\CashSession;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentDetail;
use App\Models\Tenant\PoolTable;
use App\Models\Tenant\Service;
use App\Models\Tenant\TableRental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
class PoolTableController extends Controller
{
    /** GET /api/tables */
    public function index(Request $request)
    {
        $status  = $request->query('status');   // in_progress|paused|completed|cancelled|available
        $number  = $request->query('number');   // exacto
        $perPage = (int) $request->query('per_page', 15);

        $items = PoolTable::query()
            ->with('status', 'type')
            ->statusName($status)
            ->number($number)
            ->orderBy('number')
            ->paginate($perPage);

        return response()->json($items);
    }

    /** POST /api/tables */
    public function store(Request $request)
    {
        $data = $request->validate([
            'number'        => ['required', 'integer', 'min:1', 'unique:tables,number'],
            'name'          => ['required', 'string', 'max:255'],

            // tipo de mesa
            'type_id'       => ['nullable', 'integer', 'exists:table_types,id'],
            'type'          => ['nullable', 'string', 'max:100'],

            // montos / tiempo
            'amount'        => ['nullable', 'numeric', 'min:0'],
            'consumption'   => ['nullable', 'numeric', 'min:0'],
            'rate_per_hour' => ['nullable', 'numeric', 'min:0'],

            'status'        => ['nullable', 'string', Rule::in([
                PoolTable::ST_AVAILABLE,
                PoolTable::ST_IN_PROGRESS,
                PoolTable::ST_PAUSED,
                PoolTable::ST_CANCELLED,
            ])],
            'start_time'    => ['nullable', 'date'],
            'end_time'      => ['nullable', 'date', 'after_or_equal:start_time'],
        ]);

        // Resolver type_id desde type/name si hace falta
        $typeId = $data['type_id'] ?? null;

        if (!$typeId) {
            if (!empty($data['type'])) {
                $typeId = DB::table('table_types')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['type']))])
                    ->value('id');
                if (!$typeId) {
                    return response()->json([
                        'message' => 'Tipo de mesa inválido.',
                        'errors'  => ['type' => ['El tipo especificado no existe en table_types.']],
                    ], 422);
                }
            } else {
                $typeId = DB::table('table_types')->where('name', 'Pool')->value('id');
                if (!$typeId) {
                    return response()->json([
                        'message' => 'No se pudo determinar el tipo de mesa (table_types vacío).',
                        'errors'  => ['type_id' => ['Debe enviar type_id o type válido.']],
                    ], 422);
                }
            }
        }

        $table = new PoolTable();
        $table->fill([
            'number'        => $data['number'],
            'name'          => $data['name'],
            'type_id'       => $typeId,
            'amount'        => $data['amount']        ?? 0,
            'consumption'   => $data['consumption']   ?? 0,
            'rate_per_hour' => $data['rate_per_hour'] ?? 0,
            'start_time'    => $data['start_time']    ?? null,
            'end_time'      => $data['end_time']      ?? null,
        ]);

        // Status por defecto available
        if (!empty($data['status'])) {
            $table->setStatusByName($data['status']);
        } else {
            $table->setStatusByName(PoolTable::ST_AVAILABLE);
        }

        $table->save();
        $table->load(['status', 'type']);

        return response()->json($table, 201);
    }

    /** GET /api/tables/{table} */
    public function show(PoolTable $table)
    {
        $table->load('status', 'type');
        // (Opcional) Anexar alquiler abierto actual si existe
        $openRental = TableRental::where('table_id', $table->id)->where('status', 'open')->first();
        return response()->json([
            'table'  => $table,
            'rental' => $openRental,
        ]);
    }

    /** PUT/PATCH /api/tables/{table} */
    public function update(Request $request, PoolTable $table)
    {
        $data = $request->validate([
            'number'      => ['nullable', 'integer', 'min:1', Rule::unique('tables','number')->ignore($table->id)], // << corregido
            'name'        => ['nullable', 'string', 'max:255'],
            'amount'      => ['nullable', 'numeric', 'min:0'],
            'consumption' => ['nullable', 'numeric', 'min:0'],
            'status'      => ['nullable', 'string', Rule::in([
                PoolTable::ST_AVAILABLE,
                PoolTable::ST_IN_PROGRESS,
                PoolTable::ST_PAUSED,
                PoolTable::ST_CANCELLED,
            ])],
            'start_time'  => ['nullable', 'date'],
            'end_time'    => ['nullable', 'date', 'after_or_equal:start_time'],
        ]);

        $table->fill(array_filter([
            'number'      => $data['number']      ?? null,
            'name'        => $data['name']        ?? null,
            'amount'      => $data['amount']      ?? null,
            'consumption' => $data['consumption'] ?? null,
            'start_time'  => $data['start_time']  ?? null,
            'end_time'    => $data['end_time']    ?? null,
        ], fn($v) => !is_null($v)));

        if (!empty($data['status'])) {
            $table->setStatusByName($data['status']);
        }

        $table->save();
        $table->load('status', 'type');

        return response()->json($table);
    }

    /** DELETE /api/tables/{table} */
    public function destroy(PoolTable $table)
    {
        $table->delete();
        return response()->json(['deleted' => true]);
    }

    /* ================= Acciones de flujo ================= */

    /** POST /api/tables/{table}/start */
    public function start(PoolTable $table)
    {
        return DB::transaction(function () use ($table) {
            // Releer y bloquear la mesa
            $table = PoolTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            // Idempotente: si ya está en progreso, devuelve alquiler abierto si existe
            if ($table->hasStatus(PoolTable::ST_IN_PROGRESS)) {
                $openRental = TableRental::where('table_id', $table->id)->where('status', 'open')->first();
                return response()->json([
                    'table'  => $table->fresh('status', 'type'),
                    'rental' => $openRental,
                ]);
            }

            // Solo iniciar si está disponible
            if (!$table->hasStatus(PoolTable::ST_AVAILABLE)) {
                return response()->json(['message' => 'Solo puedes iniciar una mesa disponible'], 409);
            }

            // Evitar doble alquiler abierto
            $existsOpen = TableRental::where('table_id', $table->id)->where('status', 'open')->lockForUpdate()->exists();
            if ($existsOpen) {
                // Sincronizar estado de mesa por seguridad
                $table->setStatusByName(PoolTable::ST_IN_PROGRESS);
                $table->save();

                $openRental = TableRental::where('table_id', $table->id)->where('status', 'open')->first();
                return response()->json([
                    'table'  => $table->fresh('status', 'type'),
                    'rental' => $openRental,
                ], 200);
            }

            $now = now();

            // Crear alquiler (histórico)
            $rental = TableRental::create([
                'table_id'      => $table->id,
                'started_at'    => $now,
                'rate_per_hour' => (float) ($table->rate_per_hour ?? 0),
                'status'        => 'open',
            ]);

            // Actualizar "snapshot" en la mesa
            $table->start_time = $now;
            $table->end_time   = null;
            $table->amount     = 0;
            $table->consumption = 0;
            $table->setStatusByName(PoolTable::ST_IN_PROGRESS);
            $table->save();

            return response()->json([
                'table'  => $table->fresh('status', 'type'),
                'rental' => $rental,
            ], 201);
        });
    }

    /** POST /api/tables/{table}/pause */
    public function pause(PoolTable $table)
    {
        // Pausa deshabilitada por negocio
        return response()->json(['message' => 'La función de pausa está deshabilitada'], 409);
    }

    /** POST /api/tables/{table}/resume */
    public function resume(PoolTable $table)
    {
        // Reanudar deshabilitado por negocio
        return response()->json(['message' => 'La función de reanudar está deshabilitada'], 409);
    }

    /** POST /api/tables/{table}/finish */
    /** POST /api/tables/{table}/finish */
    public function finish(PoolTable $table, Request $request)
    {
        \Illuminate\Support\Facades\Log::info($request->all());

        // 👉 Validación extendida para POS de productos
        $data = $request->validate([
            'consumption'     => ['nullable', 'numeric', 'min:0'],
            'payment_method'  => ['required', 'in:cash,card,transfer,other'],
            'rate_per_hour'   => ['nullable', 'numeric', 'min:0'],
            'discount'        => ['nullable', 'numeric', 'min:0'],
            'surcharge'       => ['nullable', 'numeric', 'min:0'],

            // Detalle POS (opcional). Si viene, se usará en lugar de "consumption" simple.
            'items'                   => ['sometimes','array'],
            'items.*.product_id'      => ['required_with:items','integer','exists:products,id'],
            'items.*.qty'             => ['required_with:items','numeric','min:1'],
            'items.*.unit_price'      => ['required_with:items','numeric','min:0'],
            'items.*.warehouse_id'    => ['nullable','integer','exists:warehouses,id'],

            // Almacén global (opcional si cada item trae su warehouse_id)
            'warehouse_id'            => ['nullable','integer','exists:warehouses,id'],

            // Permite saldo negativo si es true
            'allow_negative_stock'    => ['sometimes','boolean'],
        ]);

        $items = collect($request->input('items', []));
        $globalWarehouseId  = $request->integer('warehouse_id') ?: null;
        $allowNegativeStock = $request->boolean('allow_negative_stock', false);

        return DB::transaction(function () use ($table, $data, $items, $globalWarehouseId, $allowNegativeStock) {
            // Relee y bloquea la mesa
            $table = PoolTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            // Debe existir un alquiler abierto
            $rental = TableRental::where('table_id', $table->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (!$rental) {
                return response()->json(['message' => 'No existe un alquiler abierto para esta mesa'], 409);
            }

            $now = now();

            // 1) Cerrar tiempos y calcular importes de tiempo
            $table->end_time = $now;

            if (isset($data['rate_per_hour'])) {
                $table->rate_per_hour = (float) $data['rate_per_hour'];
            }

            $billableMins = $table->computeBillableMinutes($now, PoolTable::ROUND_BLOCK_MIN);
            $amountTime   = $table->computeAmount($now, PoolTable::ROUND_BLOCK_MIN); // solo tiempo

            if (method_exists($table, 'isFillable') && $table->isFillable('final_seconds')) $table->final_seconds = $billableMins * 60;
            if (method_exists($table, 'isFillable') && $table->isFillable('final_amount'))  $table->final_amount  = $amountTime;

            $table->amount = $amountTime;

            // 1.a) Consumo: si vienen "items" (POS), sobreescribe consumo con la suma de líneas
            $consumptionFromItems = 0.0;
            if ($items->isNotEmpty()) {
                $consumptionFromItems = round($items->sum(fn ($i) => (float)$i['qty'] * (float)$i['unit_price']), 2);
                $table->consumption   = $consumptionFromItems;
            } else {
                if (array_key_exists('consumption', $data) && $data['consumption'] !== null) {
                    $table->consumption = (float) $data['consumption'];
                }
            }

            // 1.b) Actualizar Rental con foto final
            $rental->ended_at        = $now;
            $rental->elapsed_seconds = $rental->started_at ? $rental->started_at->diffInSeconds($now) : ($billableMins * 60);
            $rental->rate_per_hour   = (float) ($data['rate_per_hour'] ?? $table->rate_per_hour ?? $rental->rate_per_hour ?? 0);
            $rental->amount_time     = $amountTime;
            $rental->consumption     = (float) ($table->consumption ?? 0);
            $rental->discount        = (float) ($data['discount']  ?? 0);
            $rental->surcharge       = (float) ($data['surcharge'] ?? 0);
            $rental->total           = round($rental->amount_time + $rental->consumption - $rental->discount + $rental->surcharge, 2);
            $rental->status          = 'closed';
            $rental->save();

            // 1.c) Dejar mesa disponible nuevamente
            $table->setStatusByName(PoolTable::ST_AVAILABLE);
            $table->save();

            // 2) Totales para el documento
            $serviceTotal = $rental->amount_time;           // tiempo
            $consumption  = (float) $rental->consumption;   // consumo (de items o simple)
            $docTotal     = round($serviceTotal + $consumption, 2);

            // 3) Validar caja abierta si pago cash
            $method        = $data['payment_method'];
            $cashSessionId = null;

            if ($method === 'cash') {
                $session = CashSession::currentFor(auth()->id());
                if (!$session) {
                    return response()->json(['message' => 'Debes abrir caja antes de cobrar en efectivo'], 422);
                }
                $cashSessionId = $session->id;
            }

            // 4) Correlativo de Nota de Venta
            $series     = 'NV01';
            $lastNumber = Document::where('type', 'sale_note')
                ->where('series', $series)
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number') ?? 0;
            $nextNumber = $lastNumber + 1;

            // 5) Crear documento (Nota de Venta)
            $doc = Document::create([
                'type'            => 'sale_note',
                'series'          => $series,
                'number'          => $nextNumber,
                'issue_date'      => $now,
                'currency'        => 'PEN',
                'subtotal'        => $docTotal, // ajusta si manejas IGV
                'tax'             => 0,
                'total'           => $docTotal,
                'payment_method'  => $method,
                'status'          => 'issued',
                'cash_session_id' => $cashSessionId,
                'meta'            => [
                    'pool_table' => $table->number,
                    'duration'   => $table->duration_human,
                    'rental_id'  => $rental->id,
                ],
            ]);

            // 6) Detalles del documento
            $serviceId = optional(Service::where('code', 'POOL_TIME')->first())->id;

            DocumentDetail::create([
                'document_id' => $doc->id,
                'description' => "Alquiler mesa #{$table->number} ({$table->duration_human})",
                'item_type'   => 'service',
                'item_id'     => $serviceId,
                'quantity'    => round($billableMins / 60, 3), // horas
                'unit'        => 'hour',
                'unit_price'  => (float) ($table->rate_per_hour ?? 0),
                'line_total'  => $serviceTotal,
                'tax'         => 0,
                'discount'    => 0,
            ]);

            // 6.b) Si hay items del POS: crear una línea por producto
            if ($items->isNotEmpty()) {
                foreach ($items as $it) {
                    $product = Product::with('unit')->findOrFail($it['product_id']);
                    $qty     = (float)$it['qty'];
                    $price   = (float)$it['unit_price'];
                    $unit    = optional($product->unit)->name ?: 'unit';

                    DocumentDetail::create([
                        'document_id' => $doc->id,
                        'description' => $product->name,
                        'item_type'   => 'product',
                        'item_id'     => $product->id,
                        'quantity'    => $qty,
                        'unit'        => $unit,
                        'unit_price'  => $price,
                        'line_total'  => round($qty * $price, 2),
                        'tax'         => 0,
                        'discount'    => 0,
                    ]);
                }
            }
            // Si NO hay items y sí hubo consumo simple, mantener tu línea de “Consumo”
            else if ($consumption > 0) {
                $consServiceId = optional(Service::where('code', 'CONSUMPTION')->first())->id;

                DocumentDetail::create([
                    'document_id' => $doc->id,
                    'description' => 'Consumo',
                    'item_type'   => 'service',
                    'item_id'     => $consServiceId,
                    'quantity'    => 1,
                    'unit'        => 'unit',
                    'unit_price'  => $consumption,
                    'line_total'  => $consumption,
                    'tax'         => 0,
                    'discount'    => 0,
                ]);
            }

            // 7) Movimiento de caja si fue efectivo
            if ($method === 'cash' && $cashSessionId) {
                CashMovement::create([
                    'cash_session_id' => $cashSessionId,
                    'type'            => 'sale',
                    'amount'          => $doc->total,
                    'reference_type'  => 'Document',
                    'reference_id'    => $doc->id,
                    'description'     => "NV {$doc->series}-{$doc->number} Mesa #{$table->number}",
                ]);
            }

            // 7.b) 👉👉 KARDEX + STOCK por ítem (solo si vinieron items)
            if ($items->isNotEmpty()) {
                foreach ($items as $it) {
                    $productId = (int)$it['product_id'];
                    $qtyOut    = (float)$it['qty'];
                    $whId      = $it['warehouse_id'] ?? $globalWarehouseId;

                    if (!$whId) {
                        return response()->json(['message' => "warehouse_id requerido para el producto {$productId}"], 422);
                    }

                    // Stock por almacén (bloqueado)
                    $ps = ProductStock::where('product_id', $productId)
                        ->where('warehouse_id', $whId)
                        ->lockForUpdate()
                        ->first();

                    if (!$ps) {
                        // Si no existe registro de stock, inicializa en 0
                        $ps = ProductStock::create([
                            'product_id'   => $productId,
                            'warehouse_id' => $whId,
                            'quantity'     => 0,
                        ]);
                    }

                    // Validar stock suficiente (opcional)
                    if (!$allowNegativeStock && (float)$ps->quantity < $qtyOut) {
                        return response()->json([
                            'message' => 'Stock insuficiente para salida',
                            'errors'  => [
                                'items' => ["Producto ID {$productId}: stock {$ps->quantity} < salida {$qtyOut}"]
                            ],
                        ], 422);
                    }

                    // Obtener costo promedio vigente desde último Kardex o fallback al costo por defecto del producto
                    $prevKardex = KardexEntry::where('product_id', $productId)
                        ->where('warehouse_id', $whId)
                        ->orderByDesc('movement_date')
                        ->orderByDesc('id')
                        ->first();

                    $product      = Product::find($productId);
                    $prevAvgCost  = $prevKardex->balance_avg_unit_cost ?? (float)($product->default_cost_price ?? 0);
                    $prevQty      = (float) $ps->quantity;

                    // Actualizar stock
                    $newQty = $prevQty - $qtyOut;
                    $ps->quantity = $newQty;
                    $ps->save();

                    // Registrar SALIDA en Kardex (costo promedio no cambia en salida)
                    $unitCost           = $prevAvgCost;                  // costo usado para valorar la salida
                    $totalCost          = round($unitCost * $qtyOut, 4);  // costo total de la salida
                    $newBalanceTotal    = round($newQty * $prevAvgCost, 4);

                    KardexEntry::create([
                        'product_id'              => $productId,
                        'warehouse_id'            => $whId,
                        'movement'                => 'out', // SALIDA
                        'quantity_in'             => 0,
                        'quantity_out'            => $qtyOut,
                        'unit_cost'               => $unitCost,
                        'total_cost'              => $totalCost,
                        'balance_qty'             => $newQty,
                        'balance_avg_unit_cost'   => $prevAvgCost,
                        'balance_total_cost'      => $newBalanceTotal,
                        'document_type'           => Document::class,
                        'document_id'             => $doc->id,
                        'movement_date'           => $now,
                        'reference'               => "NV {$doc->series}-{$doc->number}",
                        'description'             => "Salida por consumo mesa #{$table->number}",
                        'created_by'              => auth()->id(),
                    ]);
                }
            }

            // 8) Respuesta
            $table->load('status', 'type');

            return response()->json([
                'table'    => $table,
                'rental'   => $rental,
                'document' => [
                    'id'              => $doc->id,
                    'series'          => $doc->series,
                    'number'          => $doc->number,
                    'total'           => $doc->total,
                    'payment_method'  => $doc->payment_method,
                    'cash_session_id' => $doc->cash_session_id,
                ],
            ]);
        });
    }


    /** POST /api/tables/{table}/cancel */
    public function cancel(PoolTable $table)
    {
        return DB::transaction(function () use ($table) {
            $table = PoolTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            // Idempotente: si ya está cancelada, retorna
            if ($table->hasStatus(PoolTable::ST_CANCELLED)) {
                return response()->json($table->fresh('status', 'type'));
            }

            // Política: cancelar si está disponible o en progreso
            if (!$table->hasStatus(PoolTable::ST_AVAILABLE) && !$table->hasStatus(PoolTable::ST_IN_PROGRESS)) {
                return response()->json(['message' => 'No se puede cancelar en este estado'], 409);
            }

            // Si hay alquiler abierto, cancelarlo también
            $openRental = TableRental::where('table_id', $table->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($openRental) {
                $openRental->ended_at  = now();
                $openRental->status    = 'cancelled';
                $openRental->total     = 0;
                $openRental->amount_time = 0;
                $openRental->consumption = 0;
                $openRental->save();
            }

            // Reset en la mesa
            $table->end_time    = now();
            $table->amount      = 0;
            $table->consumption = 0;
            $table->setStatusByName(PoolTable::ST_CANCELLED);
            $table->save();

            return response()->json($table->fresh('status', 'type'));
        });
    }

    // =================== NUEVO: SUBIR PORTADA ===================
    /** POST /api/tables/{table}/cover  (campo: image) */
    public function uploadCover(Request $request, PoolTable $table)
    {
        // Log útil para depurar
        Log::info('allFiles', $request->allFiles()); // te mostrará la(s) llave(s) reales

        // Toma el archivo bajo "image" o, si no existe, la primera entrada de archivos
        $files = $request->allFiles();
        $file  = $files['image'] ?? (count($files) ? reset($files) : null);

        // Valida contra una llave normalizada "image"
        $validator = Validator::make(['image' => $file], [
            'image' => ['required','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],
        ], [
            'image.required' => 'Debes adjuntar una imagen.',
            'image.image'    => 'El archivo debe ser una imagen.',
            'image.mimes'    => 'Formatos permitidos: jpg, jpeg, png, webp, avif.',
            'image.max'      => 'La imagen no debe superar 5MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'input_keys' => array_keys($request->all()), // ayuda a depurar
            ], 422);
        }

        $file = $validator->validated()['image'];

        // Carpeta destino (con tenant si aplica)
        $dir = "tables/{$table->id}";
        if (function_exists('tenant') && tenant('id')) {
            $dir = "tenants/".tenant('id')."/tables/{$table->id}";
        }

        // Guardar públicamente
        $path = $file->storePublicly($dir, 'public');

        // Borrar anterior si existía
        if (!empty($table->cover_path) && Storage::disk('public')->exists($table->cover_path)) {
            Storage::disk('public')->delete($table->cover_path);
        }

        // Persistir
        $table->cover_path = $path;
        $table->save();

        return response()->json([
            'message' => 'Portada actualizada',
            'url'     => Storage::disk('public')->url($path),
            'path'    => $path,
            'table'   => $table->fresh(['status','type']),
        ], 201);
    }

    // =================== NUEVO: ELIMINAR PORTADA ===================
    /** DELETE /api/tables/{table}/cover */
    public function destroyCover(PoolTable $table)
    {
        if ($table->cover_path && Storage::disk('public')->exists($table->cover_path)) {
            Storage::disk('public')->delete($table->cover_path);
        }

        $table->cover_path = null;
        $table->save();

        return response()->json([
            'message' => 'Portada eliminada',
            'table'   => $table->fresh(['status','type']),
        ]);
    }
}
