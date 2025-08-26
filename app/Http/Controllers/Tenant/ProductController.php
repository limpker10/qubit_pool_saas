<?php
// app/Http/Controllers/API/ProductController.php
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Acepta per_page y perPage
        $perPage = (int) ($request->input('per_page') ?? $request->input('perPage', 15));
        $perPage = max(1, min(100, $perPage));

        // Acepta search null/empty
        $search = trim((string) $request->input('search', ''));

        $q = \App\Models\Tenant\Product::query()
            ->with(['category:id,name', 'unit:id,name'])
            ->when($search !== '', function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('only_active'), fn ($qq) => $qq->where('is_active', true))
            ->orderByDesc('id');

        if ($request->boolean('paginate', true) === false) {
            return response()->json($q->limit($request->integer('limit', 50))->get());
        }

        // Laravel toma `page` automáticamente del query (?page=1)
        return response()->json($q->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'sku' => ['required','string','max:100','unique:products,sku'],
            'barcode' => ['nullable','string','max:255','unique:products,barcode'],
            'description' => ['nullable','string'],
            'brand' => ['nullable','string','max:255'],
            'category_id' => ['required','exists:categories,id'],
            'unit_id' => ['required','exists:units,id'],
            'default_cost_price' => ['nullable','numeric','min:0'],
            'default_sale_price' => ['nullable','numeric','min:0'],
            'min_stock' => ['nullable','integer','min:0'],
            'is_active' => ['boolean'],
        ]);

        $product = Product::create($data);
        return response()->json($product->load(['category','unit']), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category','unit','stocks.warehouse']));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'sku' => ['sometimes','string','max:100', Rule::unique('products','sku')->ignore($product->id)],
            'barcode' => ['nullable','string','max:255', Rule::unique('products','barcode')->ignore($product->id)],
            'description' => ['nullable','string'],
            'brand' => ['nullable','string','max:255'],
            'category_id' => ['sometimes','exists:categories,id'],
            'unit_id' => ['sometimes','exists:units,id'],
            'default_cost_price' => ['nullable','numeric','min:0'],
            'default_sale_price' => ['nullable','numeric','min:0'],
            'min_stock' => ['nullable','integer','min:0'],
            'is_active' => ['boolean'],
        ]);

        $product->update($data);
        return response()->json($product->fresh()->load(['category','unit']));
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Producto eliminado']);
    }

    // stock por almacén
    public function stock(Product $product)
    {
        $stocks = ProductStock::with('warehouse')->where('product_id',$product->id)->get();
        return response()->json($stocks);
    }
    /**
     * Buscar productos por nombre o código para autocomplete.
     * Parámetros: search (string), limit (int)
     */
    public function search(Request $request)
    {
        $query = trim($request->get('search', ''));
        $limit = (int) $request->get('limit', 10);

        $products = Product::query()
            ->when($query, function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json($products);
    }
}
