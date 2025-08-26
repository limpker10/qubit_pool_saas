<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $q = Unit::query()
            ->when($request->filled('search'), fn($qq) =>
            $qq->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('abbreviation', 'like', '%' . $request->search . '%')
            )
            ->orderBy('name');

        if ($request->boolean('paginate', true)) {
            return response()->json($q->paginate($request->integer('per_page', 15)));
        }

        return response()->json($q->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:units,name'],
            'abbreviation' => ['nullable', 'string', 'max:10'],
        ]);

        $unit = Unit::create($data);

        return response()->json($unit, 201);
    }

    public function show(Unit $unit)
    {
        return response()->json($unit);
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('units', 'name')->ignore($unit->id),
            ],
            'abbreviation' => ['nullable', 'string', 'max:10'],
        ]);

        $unit->update($data);

        return response()->json($unit);
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return response()->json(['message' => 'Unidad eliminada']);
    }
}
