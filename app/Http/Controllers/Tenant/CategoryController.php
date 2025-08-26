<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = Category::query()
            ->when($request->filled('search'), fn($qq) =>
            $qq->where('name', 'like', '%' . $request->search . '%')
            )
            ->orderBy('name');

        // Si quieres paginación
        if ($request->boolean('paginate', true)) {
            return response()->json($q->paginate($request->integer('per_page', 15)));
        }

        // Para listas en selects
        return response()->json($q->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        $category = Category::create($data);

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
        ]);

        $category->update($data);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'Categoría eliminada']);
    }
}
