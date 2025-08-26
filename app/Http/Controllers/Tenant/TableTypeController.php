<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\TableType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TableTypeController extends Controller
{
    /**
     * GET /api/table-types
     * Query params:
     *  - q: cadena de búsqueda (name/description)
     *  - sort: campo (name|created_at|updated_at) - por defecto name
     *  - dir: asc|desc - por defecto asc
     *  - per_page: número por página (si per_page = -1 devuelve todo sin paginar)
     */
    public function index(Request $request)
    {
        $q       = trim((string) $request->query('q', ''));
        $sort    = in_array($request->query('sort'), ['name','created_at','updated_at']) ? $request->query('sort') : 'name';
        $dir     = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = (int) ($request->query('per_page', 15));

        $query = TableType::query();

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $query->orderBy($sort, $dir);

        if ($perPage === -1) {
            $items = $query->get();
            return response()->json($items);
        }

        $items = $query->paginate(max(1, $perPage))->appends($request->query());
        return response()->json($items);
    }

    /**
     * POST /api/table-types
     * body: { name: string (unique), description?: string }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:table_types,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = TableType::create($data);

        return response()->json($type, 201);
    }

    /**
     * GET /api/table-types/{id}
     */
    public function show(TableType $tableType)
    {
        // Si quieres devolver cantidad de mesas asociadas:
        $tableType->loadCount('tables');

        return response()->json($tableType);
    }

    /**
     * PUT/PATCH /api/table-types/{id}
     * body: { name?: string (unique), description?: string }
     */
    public function update(Request $request, TableType $tableType)
    {
        $data = $request->validate([
            'name'        => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('table_types', 'name')->ignore($tableType->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $tableType->fill($data)->save();

        return response()->json($tableType);
    }

    /**
     * DELETE /api/table-types/{id}
     * Bloquea el borrado si hay mesas que referencian este tipo.
     */
    public function destroy(TableType $tableType)
    {
        // Verificar si hay mesas usando este tipo (sin depender del nombre del modelo)
        $inUse = DB::table('tables')->where('type_id', $tableType->id)->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar: hay mesas asociadas a este tipo.',
            ], 409);
        }

        $tableType->delete();

        return response()->json(null, 204);
    }
}
