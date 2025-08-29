<?php
// app/Http/Controllers/Tenant/TableRentalController.php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PoolTable;
use App\Models\Tenant\TableRental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableRentalController extends Controller
{
    /**
     * GET /api/table_rentals
     * Filtros: status, table_id, date_from, date_to
     * Opcionales: with_items=1, with_table=1, per_page
     */
    public function index(Request $request)
    {
        $status     = $request->query('status');                 // open|closed|cancelled
        $tableId    = (int) $request->query('table_id', 0);
        $dateFrom   = $request->query('date_from');              // YYYY-MM-DD
        $dateTo     = $request->query('date_to');                // YYYY-MM-DD
        $perPage    = (int) $request->query('per_page', 15);
        $withItems  = filter_var($request->query('with_items', false), FILTER_VALIDATE_BOOLEAN);
        $withTable  = filter_var($request->query('with_table', false), FILTER_VALIDATE_BOOLEAN);

        $q = TableRental::query()
            ->status($status)
            ->forTable($tableId);

        if ($dateFrom) $q->whereDate('started_at', '>=', $dateFrom);
        if ($dateTo)   $q->whereDate('started_at', '<=', $dateTo);

        $q->orderByDesc('started_at')->orderByDesc('id');

        $rels = [];
        if ($withTable) $rels[] = 'table:id,number,rate_per_hour';
        if ($withItems) {
            $rels['items'] = function ($qi) {
                $qi->select([
                    'table_rental_items.id',
                    'table_rental_items.table_rental_id',
                    'table_rental_items.product_id',
                    'table_rental_items.product_name',
                    'table_rental_items.unit_id',
                    'table_rental_items.unit_name',
                    'table_rental_items.qty',
                    'table_rental_items.unit_price',
                    'table_rental_items.discount',
                    'table_rental_items.total',
                    'table_rental_items.status',
                    'table_rental_items.created_at',
                ])->where('table_rental_items.status', 'ok')
                    ->orderBy('table_rental_items.id', 'asc');
            };
        }

        if (!empty($rels)) $q->with($rels);

        return response()->json($q->paginate($perPage));
    }

    /**
     * POST /api/table_rentals
     * Crea (abre) un alquiler para una mesa.
     * Query opcional: idempotent=1 -> si ya hay open, lo devuelve en vez de 409.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'table_id'      => 'required|exists:tables,id',
            'rate_per_hour' => 'nullable|numeric|min:0',
            'meta'          => 'nullable|array',
        ]);
        $idempotent = filter_var($request->query('idempotent', false), FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($data, $request, $idempotent) {
            // Bloquear mesa
            /** @var PoolTable $table */
            $table = PoolTable::whereKey($data['table_id'])->lockForUpdate()->firstOrFail();

            // ¿Ya existe un alquiler abierto?
            $open = TableRental::where('table_id', $table->id)->where('status', TableRental::ST_OPEN)->lockForUpdate()->first();

            if ($open) {
                if ($idempotent) {
                    return response()->json([
                        'table'  => $table->fresh('status', 'type'),
                        'rental' => $open,
                    ], 200);
                }
                return response()->json(['message' => 'La mesa ya tiene un alquiler abierto.'], 409);
            }

            // Crear alquiler
            $now   = now();
            $rate  = isset($data['rate_per_hour']) ? (float) $data['rate_per_hour'] : (float) ($table->rate_per_hour ?? 0);

            $rental = TableRental::create([
                'table_id'      => $table->id,
                'started_at'    => $now,
                'rate_per_hour' => $rate,
                'status'        => TableRental::ST_OPEN,
                'opened_by_id'  => $request->user()->id ?? null,
                'meta'          => $data['meta'] ?? null,
            ]);

            // Snapshot de mesa
            $table->start_time  = $now;
            $table->end_time    = null;
            $table->amount      = 0;
            $table->consumption = 0;
            $table->setStatusByName(PoolTable::ST_IN_PROGRESS); // asumiendo que existe
            $table->save();

            return response()->json([
                'table'  => $table->fresh('status','type'),
                'rental' => $rental->fresh(),
            ], 201);
        });
    }

    /**
     * GET /api/table_rentals/{rental}
     * Opcionales: with_items=1, with_table=1
     */
    public function show(Request $request, TableRental $tableRental)
    {
        $withItems = filter_var($request->query('with_items', false), FILTER_VALIDATE_BOOLEAN);
        $withTable = filter_var($request->query('with_table', false), FILTER_VALIDATE_BOOLEAN);

        $rels = [];
        if ($withTable) $rels[] = 'table:id,number,rate_per_hour';
        if ($withItems) {
            $rels['items'] = function ($qi) {
                $qi->select([
                    'table_rental_items.id',
                    'table_rental_items.table_rental_id',
                    'table_rental_items.product_id',
                    'table_rental_items.product_name',
                    'table_rental_items.unit_id',
                    'table_rental_items.unit_name',
                    'table_rental_items.qty',
                    'table_rental_items.unit_price',
                    'table_rental_items.discount',
                    'table_rental_items.total',
                    'table_rental_items.status',
                    'table_rental_items.created_at',
                ])->where('table_rental_items.status', 'ok')
                    ->orderBy('table_rental_items.id', 'asc');
            };
        }

        if (!empty($rels)) $tableRental->load($rels);

        return response()->json($tableRental);
    }

    /**
     * PUT/PATCH /api/table_rentals/{rental}
     * Permite ajustar importes mientras está OPEN (rate/discount/surcharge/meta).
     */
    public function update(Request $request, TableRental $tableRental)
    {
        $data = $request->validate([
            'rate_per_hour' => 'nullable|numeric|min:0',
            'discount'      => 'nullable|numeric|min:0',
            'surcharge'     => 'nullable|numeric|min:0',
            'consumption'   => 'nullable|numeric|min:0', // si por alguna razón quieres setearlo manual
            'meta'          => 'nullable|array',
        ]);

        if ($tableRental->status !== TableRental::ST_OPEN) {
            return response()->json(['message' => 'Solo puedes editar alquileres abiertos.'], 409);
        }

        return DB::transaction(function () use ($tableRental, $data) {
            $tableRental->fill($data);
            $tableRental->recalcTotals(true); // recalcula total en base a items + amount_time + discount/surcharge
            return response()->json($tableRental->fresh());
        });
    }

    /**
     * DELETE /api/table_rentals/{rental}
     * Por convención, interpretamos destroy como "cancelar" si está OPEN.
     * Si está CLOSED -> 409.
     */
    public function destroy(TableRental $tableRental)
    {
        if ($tableRental->status === TableRental::ST_CLOSED) {
            return response()->json(['message' => 'No se puede eliminar un alquiler cerrado.'], 409);
        }

        return DB::transaction(function () use ($tableRental) {
            $tableRental->cancel('Cancelado vía destroy()');

            // snapshot mesa: volver a disponible si corresponde
            $table = $tableRental->table;
            if ($table && $table->hasStatus(\App\Models\Tenant\PoolTable::ST_IN_PROGRESS)) {
                $table->end_time    = now();
                $table->amount      = 0;
                $table->consumption = 0;
                $table->setStatusByName(\App\Models\Tenant\PoolTable::ST_AVAILABLE);
                $table->save();
            }

            return response()->json($tableRental->fresh());
        });
    }

    /**
     * POST /api/table_rentals/{rental}/close
     * Cierra el alquiler calculando tiempos y total.
     * Body opcional: rate_per_hour, discount, surcharge, ended_at
     */
    public function close(Request $request, TableRental $tableRental)
    {
        if ($tableRental->status !== TableRental::ST_OPEN) {
            return response()->json(['message' => 'El alquiler no está abierto.'], 409);
        }

        $data = $request->validate([
            'rate_per_hour' => 'nullable|numeric|min:0',
            'discount'      => 'nullable|numeric|min:0',
            'surcharge'     => 'nullable|numeric|min:0',
            'ended_at'      => 'nullable|date',
        ]);

        return DB::transaction(function () use ($tableRental, $data, $request) {
            $tableRental->close($data);
            $tableRental->closed_by_id = $request->user()->id ?? null;
            $tableRental->save();

            // snapshot mesa: setear disponible + montos de cierre
            $table = $tableRental->table()->lockForUpdate()->first();
            if ($table) {
                $table->end_time    = $tableRental->ended_at;
                $table->final_seconds = $tableRental->elapsed_seconds ?? null;
                $table->final_amount  = $tableRental->total ?? 0;
                $table->amount        = $tableRental->amount_time ?? 0;
                $table->consumption   = $tableRental->consumption ?? 0;
                $table->setStatusByName(\App\Models\Tenant\PoolTable::ST_AVAILABLE);
                $table->save();
            }

            return response()->json([
                'rental' => $tableRental->fresh(),
                'table'  => $table ? $table->fresh('status','type') : null,
            ]);
        });
    }

    /**
     * POST /api/table_rentals/{rental}/cancel
     * Cancela el alquiler (sin cobro).
     */
    public function cancel(Request $request, TableRental $tableRental)
    {
        if ($tableRental->status !== TableRental::ST_OPEN) {
            return response()->json(['message' => 'El alquiler no está abierto.'], 409);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($tableRental, $data) {
            $tableRental->cancel($data['reason'] ?? null);

            $table = $tableRental->table()->lockForUpdate()->first();
            if ($table) {
                $table->end_time    = $tableRental->ended_at;
                $table->amount      = 0;
                $table->consumption = 0;
                $table->setStatusByName(\App\Models\Tenant\PoolTable::ST_AVAILABLE);
                $table->save();
            }

            return response()->json([
                'rental' => $tableRental->fresh(),
                'table'  => $table ? $table->fresh('status','type') : null,
            ]);
        });
    }

    /**
     * POST /api/table_rentals/{rental}/recalc
     * Fuerza recálculo de consumption/total a partir de items.
     */
    public function recalc(TableRental $tableRental)
    {
        $tableRental->recalcTotals(true);
        return response()->json($tableRental->fresh());
    }
}
