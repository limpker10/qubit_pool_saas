<?php
// app/Http/Controllers/API/KardexController.php
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\KardexEntry;
use App\Services\KardexService;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    public function __construct(private KardexService $service) {}

    public function index(Request $request)
    {
        $q = KardexEntry::with(['product','warehouse'])
            ->when($request->filled('product_id'), fn($qq)=>$qq->where('product_id',$request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn($qq)=>$qq->where('warehouse_id',$request->integer('warehouse_id')))
            ->when($request->filled('from'), fn($qq)=>$qq->whereDate('movement_date','>=',$request->date('from')))
            ->when($request->filled('to'), fn($qq)=>$qq->whereDate('movement_date','<=',$request->date('to')))
            ->orderBy('movement_date','asc')
            ->paginate($request->integer('per_page', 20));
        return response()->json($q);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required','exists:products,id'],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'movement' => ['required','in:entrada,salida,ajuste,transfer_in,transfer_out'],
            'quantity' => ['required','integer','min:1'],
            'unit_cost' => ['nullable','numeric','min:0'],
            'direction' => ['nullable','in:positivo,negativo'], // solo para ajuste
            'movement_date' => ['nullable','date'],
            'reference' => ['nullable','string','max:255'],
            'description' => ['nullable','string'],
            'document_type' => ['nullable','string','max:255'],
            'document_id' => ['nullable','integer'],
        ]);

        $entry = $this->service->record($data);
        return response()->json($entry->load(['product','warehouse']), 201);
    }
}
