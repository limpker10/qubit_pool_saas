<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $q = Warehouse::query()
            ->when($request->filled('search'), fn($qq) =>
            $qq->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('code', 'like', '%' . $request->search . '%')
            )
            ->orderBy('name');

        // Si viene paginate=false => devuelve lista completa (para selects)
        if ($request->boolean('paginate', true)) {
            return response()->json($q->paginate($request->integer('per_page', 15)));
        }

        return response()->json($q->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:50', 'unique:warehouses,code'],
            'address'   => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $warehouse = Warehouse::create($data);

        return response()->json($warehouse, 201);
    }

    public function show(Warehouse $warehouse)
    {
        return response()->json($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses', 'code')->ignore($warehouse->id),
            ],
            'address'   => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $warehouse->update($data);

        return response()->json($warehouse);
    }

    public function destroy(Warehouse $warehouse)
    {
        // Opcional: validar si tiene stock o kardex asociado antes de eliminar
        if ($warehouse->stocks()->exists() || $warehouse->stocks()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar, tiene productos asociados'
            ], 422);
        }

        $warehouse->delete();
        return response()->json(['message' => 'Almacén eliminado']);
    }
}
