<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\CashMovement;
use App\Models\Tenant\CashSession;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentDetail;
use App\Models\Tenant\KardexEntry;
use App\Models\Tenant\PoolTable;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductStock;
use App\Models\Tenant\Service;
use App\Models\Tenant\TableRental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PoolTableController extends Controller
{
    /** GET /api/tables */
    public function index(Request $request)
    {
        $status     = $request->query('status');     // in_progress|paused|completed|cancelled|available
        $number     = $request->query('number');     // exacto
        $perPage    = (int) $request->query('per_page', 15);
        $withItems  = filter_var($request->query('with_items', false), FILTER_VALIDATE_BOOLEAN);

        $items = PoolTable::query()
            ->with([
                'status',
                'type',
                'activeRental' => function ($q) use ($withItems) {
                    // Selecciona solo columnas necesarias del rental
                    $q->select('id','table_id','started_at','ended_at','rate_per_hour',
                        'amount_time','consumption','discount','surcharge','total','status','created_at');

                    // Si piden items, cárgalos (solo vigentes)
                    if ($withItems) {
                        $q->with([
                            'items' => function ($qi) {
                                $qi->select('id','table_rental_id','product_id','product_name','unit_id','unit_name',
                                    'qty','unit_price','discount','total','status','created_at')
                                    ->where('status','ok')
                                    ->orderBy('id','asc');
                            }
                        ])->withCount([
                            'items as items_count' => function ($qc) {
                                $qc->where('status','ok');
                            }
                        ]);
                    }
                },
            ])
            ->statusName($status) // asumes que ya existe el scope
            ->number($number)     // asumes que ya existe el scope
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
    public function finish(PoolTable $table, Request $request)
    {
        // 1) Validación (incluye rental_id)
        $data = $request->validate([
            'rental_id'        => ['required','integer','exists:table_rentals,id'],
            'consumption'      => ['nullable','numeric','min:0'],
            'payment_method'   => ['required','in:cash,card,transfer,other'],
            'rate_per_hour'    => ['nullable','numeric','min:0'],
            'discount'         => ['nullable','numeric','min:0'],
            'surcharge'        => ['nullable','numeric','min:0'],

            // opcional: si mandas items en el cierre, los uso en vez de leer del rental
            'items'                => ['sometimes','array'],
            'items.*.product_id'   => ['required_with:items','integer','exists:products,id'],
            'items.*.qty'          => ['required_with:items','numeric','min:1'],
            'items.*.unit_price'   => ['required_with:items','numeric','min:0'],
            'items.*.warehouse_id' => ['nullable','integer','exists:warehouses,id'],

            // almacén global por si las líneas no traen warehouse_id
            'warehouse_id'         => ['nullable','integer','exists:warehouses,id'],
            'allow_negative_stock' => ['sometimes','boolean'],
        ]);

        // 👇 NEW: tomar warehouse por defecto del usuario (Sanctum) si no viene en el request
        $userWarehouseId  = auth('sanctum')->user()->warehouse_id;  // <- requiere columna en users
        $globalWarehouseId = $request->integer('warehouse_id') ?: ($userWarehouseId ?: null);

        $allowNegativeStock = (bool) $request->boolean('allow_negative_stock', false);

        DB::beginTransaction();
        try {
            // 2) Releer mesa y rental con lock
            $table  = PoolTable::whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            /** @var TableRental $rental */
            $rental = TableRental::whereKey($data['rental_id'])->lockForUpdate()->firstOrFail();

            if ($rental->table_id !== $table->id) {
                throw ValidationException::withMessages([
                    'rental_id' => ['El alquiler no pertenece a esta mesa.'],
                ]);
            }
            if ($rental->status !== TableRental::ST_OPEN) {
                throw ValidationException::withMessages([
                    'rental_id' => ['El alquiler no está abierto.'],
                ]);
            }

            $now = now();

            // 3) Tiempos
            $table->end_time = $now;
            if (array_key_exists('rate_per_hour', $data) && $data['rate_per_hour'] !== null) {
                $table->rate_per_hour = (float) $data['rate_per_hour'];
            }
            $billableMins = $table->computeBillableMinutes($now, PoolTable::ROUND_BLOCK_MIN);
            $amountTime   = $table->computeAmount($now, PoolTable::ROUND_BLOCK_MIN); // solo tiempo
            $table->amount = $amountTime;

            // 4) Ítems a facturar/sacar de stock
            $incomingItems = collect($request->input('items', []));
            if ($incomingItems->isEmpty()) {
                // Tomar lo guardado en el rental (status=ok)
                $incomingItems = $rental->itemsAll()
                    ->where('status','ok')
                    ->get(['product_id','qty','unit_price'])
                    ->map(function ($i) use ($globalWarehouseId) {
                        return [
                            'product_id'   => (int) $i->product_id,
                            'qty'          => (float) $i->qty,
                            'unit_price'   => (float) $i->unit_price,
                            'warehouse_id' => $globalWarehouseId, // se puede sobreescribir si pasas uno global
                        ];
                    });
            }

            // 4.a) Calcular consumo desde items si hay; si no, usar "consumption" simple
            if ($incomingItems->isNotEmpty()) {
                $consumptionFromItems = round($incomingItems->sum(fn($i) => (float)$i['qty'] * (float)$i['unit_price']), 2);
                $table->consumption   = $consumptionFromItems;
            } else {
                $table->consumption   = (float) ($data['consumption'] ?? 0);
            }

            // 5) Cerrar rental (foto final)
            $rental->ended_at        = $now;
            $rental->elapsed_seconds = $rental->started_at ? $rental->started_at->diffInSeconds($now) : ($billableMins * 60);
            $rental->rate_per_hour   = (float) ($data['rate_per_hour'] ?? $table->rate_per_hour ?? $rental->rate_per_hour ?? 0);
            $rental->amount_time     = $amountTime;
            $rental->consumption     = (float) ($table->consumption ?? 0);
            $rental->discount        = (float) ($data['discount']  ?? 0);
            $rental->surcharge       = (float) ($data['surcharge'] ?? 0);
            $rental->total           = round($rental->amount_time + $rental->consumption - $rental->discount + $rental->surcharge, 2);
            $rental->status          = TableRental::ST_CLOSED;
            $rental->save();

            // 6) Mesa disponible
            $table->setStatusByName(PoolTable::ST_AVAILABLE);
            $table->save();

            // 7) Caja (si efectivo)
            $method        = $data['payment_method'];
            $cashSessionId = null;
            if ($method === 'cash') {
                $session = CashSession::currentFor(auth()->id());
                if (!$session) {
                    throw ValidationException::withMessages([
                        'payment_method' => ['Debes abrir caja antes de cobrar en efectivo.']
                    ]);
                }
                $cashSessionId = $session->id;
            }

            // 8) Documento
            $series     = 'NV01';
            $lastNumber = Document::where('type','sale_note')->where('series',$series)->lockForUpdate()->orderByDesc('number')->value('number') ?? 0;
            $nextNumber = $lastNumber + 1;

            // total documento = total del rental (tiempo + consumo - desc + recargo)
            $docTotal = $rental->total;

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
                    'discount'   => $rental->discount,
                    'surcharge'  => $rental->surcharge,
                ],
            ]);

            // 8.a) Detalle: TIEMPO
            $serviceId = optional(Service::where('code','POOL_TIME')->first())->id;
            DocumentDetail::create([
                'document_id' => $doc->id,
                'description' => "Alquiler mesa #{$table->number} ({$table->duration_human})",
                'item_type'   => 'service',
                'item_id'     => $serviceId,
                'quantity'    => round($billableMins / 60, 3),
                'unit'        => 'hour',
                'unit_price'  => (float) ($table->rate_per_hour ?? 0),
                'line_total'  => $rental->amount_time,
                'tax'         => 0,
                'discount'    => 0,
            ]);

            // 8.b) Detalle: CONSUMO
            if ($incomingItems->isNotEmpty()) {
                foreach ($incomingItems as $it) {
                    $product = Product::with('unit')->findOrFail($it['product_id']);
                    $qty     = (float) $it['qty'];
                    $price   = (float) $it['unit_price'];
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
            } elseif ($rental->consumption > 0) {
                $consServiceId = optional(Service::where('code','CONSUMPTION')->first())->id;
                DocumentDetail::create([
                    'document_id' => $doc->id,
                    'description' => 'Consumo',
                    'item_type'   => 'service',
                    'item_id'     => $consServiceId,
                    'quantity'    => 1,
                    'unit'        => 'unit',
                    'unit_price'  => $rental->consumption,
                    'line_total'  => $rental->consumption,
                    'tax'         => 0,
                    'discount'    => 0,
                ]);
            }

            // 9) Movimiento de caja
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

            // 10) STOCK + KARDEX por ítem (si hubo líneas)
            if ($incomingItems->isNotEmpty()) {
                foreach ($incomingItems as $it) {
                    $productId = (int) $it['product_id'];
                    $qtyOut    = (float) $it['qty'];
                    $whId      = $it['warehouse_id'] ?? $globalWarehouseId;

                    if (!$whId) {
                        throw ValidationException::withMessages([
                            'warehouse_id' => ["warehouse_id requerido para el producto {$productId}."]
                        ]);
                    }

                    // Stock por almacén (lock)
                    $ps = ProductStock::where('product_id', $productId)
                        ->where('warehouse_id', $whId)
                        ->lockForUpdate()
                        ->first();

                    if (!$ps) {
                        $ps = ProductStock::create([
                            'product_id'   => $productId,
                            'warehouse_id' => $whId,
                            'quantity'     => 0,
                        ]);
                    }

                    if (!$allowNegativeStock && (float)$ps->quantity < $qtyOut) {
                        throw ValidationException::withMessages([
                            'items' => ["Producto ID {$productId}: stock {$ps->quantity} insuficiente para salida {$qtyOut}."]
                        ]);
                    }

                    // Costo promedio vigente
                    $prevKardex = KardexEntry::where('product_id', $productId)
                        ->where('warehouse_id', $whId)
                        ->orderByDesc('movement_date')
                        ->orderByDesc('id')
                        ->first();

                    $product     = Product::find($productId);
                    $prevAvgCost = $prevKardex->balance_avg_unit_cost ?? (float) ($product->default_cost_price ?? 0);
                    $prevQty     = (float) $ps->quantity;

                    // Actualiza stock
                    $newQty = $prevQty - $qtyOut;
                    $ps->quantity = $newQty;
                    $ps->save();

                    // Kardex SALIDA
                    $unitCost         = $prevAvgCost;
                    $totalCost        = round($unitCost * $qtyOut, 4);
                    $newBalanceTotal  = round($newQty * $prevAvgCost, 4);

                    KardexEntry::create([
                        'product_id'            => $productId,
                        'warehouse_id'          => $whId,
                        'movement'              => 'salida',
                        'quantity_in'           => 0,
                        'quantity_out'          => $qtyOut,
                        'unit_cost'             => $unitCost,
                        'total_cost'            => $totalCost,
                        'balance_qty'           => $newQty,
                        'balance_avg_unit_cost' => $prevAvgCost,
                        'balance_total_cost'    => $newBalanceTotal,
                        'document_type'         => Document::class,
                        'document_id'           => $doc->id,
                        'movement_date'         => $now,
                        'reference'             => "NV {$doc->series}-{$doc->number}",
                        'description'           => "Salida por consumo mesa #{$table->number}",
                        'created_by'            => auth()->id(),
                    ]);
                }
            }

            // 11) Respuesta
            $table->load('status','type');
            DB::commit();

            return response()->json([
                'table'    => $table,
                'rental'   => $rental->fresh(),
                'document' => [
                    'id'     => $doc->id,
                    'series' => $doc->series,
                    'number' => $doc->number,
                    'total'  => $doc->total,
                    'payment_method'  => $doc->payment_method,
                    'cash_session_id' => $doc->cash_session_id,
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);

            if ($e instanceof ValidationException) {
                throw $e; // 422 con mensajes y rollback
            }

            return response()->json([
                'message' => 'No se pudo finalizar la mesa',
                'error'   => $e->getMessage(),
            ], 500);
        }
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
        // Log mínimo y seguro
        Log::info('uploadCover.files', ['keys' => array_keys($request->allFiles())]);

        // Tomar el archivo bajo "image" o el primero que llegue
        $file = $request->file('image') ?? collect($request->allFiles())->first();

        // Validación
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
                'message'    => $validator->errors()->first(),
                'input_keys' => array_keys($request->all()),
            ], 422);
        }

        $file = $validator->validated()['image'];

        // Siempre ruta relativa a app/public del *tenant* (NO anteponer "tenants/...")
        $dir = "tables/{$table->id}";

        // Guardar con visibilidad pública en el disco tenant-aware
        // storePublicly() genera un nombre hash y crea directorios si faltan
        $path = $file->storePublicly($dir, 'public'); // p.ej. "tables/1/abc123.jpg"

        // Borrar anterior si existe (limpiando si alguna vez guardaste con "tenants/...").
        if (!empty($table->cover_path)) {
            $old = preg_replace('#^tenants/[^/]+/#', '', $table->cover_path);
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $coverUrl = tenant_asset($path);
        $table->cover_path = $coverUrl;
        $table->save();

        return response()->json([
            'message'     => 'Portada actualizada',
            'cover_path'  => $path,
            'table'       => $table->fresh(['status','type']),
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
