<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\CashSession;
use App\Models\Tenant\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashMovementController extends Controller
{
    /** GET /api/cash-sessions/{cash_session}/movements */
    public function index(Request $request, CashSession $cash_session)
    {
        $this->authorizeOwner($request, $cash_session);

        $movs = CashMovement::where('cash_session_id', $cash_session->id)
            ->orderBy('created_at')->get();

        return response()->json(['movements' => $movs]);
    }

    /** POST /api/cash-sessions/{cash_session}/movements */
    public function store(Request $request, CashSession $cash_session)
    {
        $this->authorizeOwner($request, $cash_session);

        if ($cash_session->status !== 'open') {
            return response()->json(['message' => 'La caja no está abierta.'], 422);
        }

        $data = $request->validate([
            'type'        => ['required', Rule::in(['income','expense','withdrawal','refund','adjust','sale'])],
            'amount'      => ['required','numeric','min:0'],
            'description' => ['nullable','string','max:255'],
        ]);

        $amount = (float)$data['amount'];
        $outflowTypes = ['expense','withdrawal','refund'];
        if (in_array($data['type'], $outflowTypes, true)) {
            $amount = -abs($amount);
        }
        // 'adjust' puede ser +/-: si viene 'amount_signed', lo respetamos
        if ($data['type'] === 'adjust' && $request->has('amount_signed')) {
            $amount = (float)$request->input('amount_signed');
        }

        $m = CashMovement::create([
            'cash_session_id' => $cash_session->id,
            'type'            => $data['type'],
            'amount'          => $amount,
            'description'     => $data['description'] ?? null,
        ]);

        return response()->json(['movement' => $m], 201);
    }

    /** DELETE /api/cash-sessions/{cash_session}/movements/{movement} */
    public function destroy(Request $request, CashSession $cash_session, CashMovement $movement)
    {
        $this->authorizeOwner($request, $cash_session);

        if ($cash_session->status !== 'open') {
            return response()->json(['message' => 'La caja no está abierta.'], 422);
        }

        // Evita borrar 'open' y, si quieres, 'sale' (de documentos) para no descuadrar ventas.
        if (in_array($movement->type, ['open','sale'], true)) {
            return response()->json(['message' => 'No se puede eliminar este tipo de movimiento.'], 422);
        }

        if ((int)$movement->cash_session_id !== (int)$cash_session->id) {
            abort(404);
        }

        $movement->delete();

        return response()->json(['deleted' => true]);
    }

    protected function authorizeOwner(Request $request, CashSession $session): void
    {
        if ((int)$session->user_id !== (int)$request->user()->id) {
            abort(403, 'No autorizado');
        }
    }
}
