<?php
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\CashSession;
use App\Models\Tenant\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CashSessionController extends Controller
{
    /**
     * GET /api/cash-sessions
     * - ?current=1  -> retorna {session, movements} de la sesión abierta del usuario.
     * - ?status=open|closed -> lista filtrada del usuario.
     * - ?include=movements -> incluye movimientos (solo si current=1 o show()).
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        if ($request->boolean('current')) {
            $session = CashSession::where('user_id', $userId)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            $movements = [];
            if ($session) {
                $movements = CashMovement::where('cash_session_id', $session->id)
                    ->orderBy('created_at')->get();
            }

            return response()->json([
                'session'   => $session,
                'movements' => $movements,
            ]);
        }

        $q = CashSession::where('user_id', $userId)->latest('opened_at');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        // Puedes paginar si prefieres: ->paginate(20)
        $list = $q->get();

        return response()->json(['data' => $list]);
    }

    /**
     * GET /api/cash-sessions/{session}
     * - ?include=movements para anexar movimientos.
     */
    public function show(Request $request, CashSession $cashSession)
    {
        $this->authorizeSession($request, $cashSession);

        $payload = ['session' => $cashSession];

        if ($request->query('include') === 'movements') {
            $payload['movements'] = CashMovement::where('cash_session_id', $cashSession->id)
                ->orderBy('created_at')
                ->get();
        }

        return response()->json($payload);
    }

    /** Retorna la sesión abierta actual del usuario + movimientos */
    public function current(Request $request)
    {
        $userId = $request->user()->id;
        $session = CashSession::where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $movements = [];
        if ($session) {
            $movements = CashMovement::where('cash_session_id', $session->id)
                ->orderBy('created_at')
                ->get();
        }

        return response()->json([
            'session'   => $session,
            'movements' => $movements,
        ]);
    }

    /** Abre una nueva sesión de caja para el usuario autenticado */
    public function open(Request $request)
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $userId = $request->user()->id;

        // Verifica que no tenga otra caja abierta
        $existing = CashSession::where('user_id', $userId)->where('status', 'open')->exists();
        if ($existing) {
            return response()->json(['message' => 'Ya tienes una caja abierta.'], 422);
        }

        $session = DB::transaction(function () use ($userId, $data) {
            $session = CashSession::create([
                'user_id'      => $userId,
                'opened_at'    => now(),
                'opening_cash' => $data['opening_cash'],
                'expected_cash'=> 0,
                'status'       => 'open',
            ]);

            CashMovement::create([
                'cash_session_id' => $session->id,
                'type'            => 'open',
                'amount'          => (float) $data['opening_cash'], // entra efectivo
                'description'     => 'Apertura de caja',
            ]);

            return $session;
        });

        $movements = CashMovement::where('cash_session_id', $session->id)->orderBy('created_at')->get();

        return response()->json([
            'session'   => $session->fresh(),
            'movements' => $movements,
        ], 201);
    }

    /** Lista movimientos de la sesión (solo del dueño) */
    public function movements(Request $request, CashSession $session)
    {
        $this->authorizeSession($request, $session);
        $movements = CashMovement::where('cash_session_id', $session->id)->orderBy('created_at')->get();
        return response()->json(['movements' => $movements]);
    }

    /** Agrega un movimiento manual a la sesión abierta */
    public function addMovement(Request $request, CashSession $session)
    {
        $this->authorizeSession($request, $session);
        if ($session->status !== 'open') {
            return response()->json(['message' => 'La caja no está abierta.'], 422);
        }

        $data = $request->validate([
            'type'        => ['required', Rule::in(['income','expense','withdrawal','refund','adjust','sale','open'])],
            'amount'      => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        // Normaliza signo según tipo (gasto/egreso negativos, ingresos positivos)
        $amount = (float) $data['amount'];
        $outflowTypes = ['expense','withdrawal','refund'];
        if (in_array($data['type'], $outflowTypes, true)) {
            $amount = -abs($amount);
        }
        // 'adjust' puede ser +/-; lo dejamos tal cual si el cliente envía el signo explícito
        if ($data['type'] === 'adjust') {
            $amount = $request->has('amount_signed') ? (float) $request->input('amount_signed') : $amount;
        }

        CashMovement::create([
            'cash_session_id' => $session->id,
            'type'            => $data['type'],
            'amount'          => $amount,
            'description'     => $data['description'] ?? null,
        ]);

        $movements = CashMovement::where('cash_session_id', $session->id)->orderBy('created_at')->get();
        return response()->json(['movements' => $movements], 201);
    }

    /** Cierra la sesión: calcula esperado, setea contado y diferencia, y opcionalmente crea ajuste */
    public function close(Request $request, CashSession $session)
    {
        $this->authorizeSession($request, $session);
        if ($session->status !== 'open') {
            return response()->json(['message' => 'La caja ya está cerrada.'], 422);
        }

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'create_adjust'=> ['nullable', 'boolean'],
        ]);

        $session = DB::transaction(function () use ($session, $data) {
            // esperado = suma de todos los movimientos (incluida apertura)
            $sum = CashMovement::where('cash_session_id', $session->id)->sum('amount');
            $expected = (float) $sum;
            $counted  = (float) $data['counted_cash'];
            $diff     = round($counted - $expected, 2);

            $session->update([
                'closed_at'     => now(),
                'expected_cash' => $expected,
                'counted_cash'  => $counted,
                'difference'    => $diff,
                'status'        => 'closed',
            ]);

            if (!empty($data['create_adjust']) && abs($diff) > 0.009) {
                // Creamos ajuste inverso para cuadrar contablemente
                CashMovement::create([
                    'cash_session_id' => $session->id,
                    'type'            => 'adjust',
                    'amount'          => -$diff,
                    'description'     => 'Ajuste por cierre de caja',
                ]);
            }

            return $session;
        });

        $movements = CashMovement::where('cash_session_id', $session->id)->orderBy('created_at')->get();

        return response()->json([
            'session'   => $session->fresh(),
            'movements' => $movements,
        ]);
    }

    /** Asegura que la sesión pertenezca al usuario autenticado */
    protected function authorizeSession(Request $request, CashSession $session): void
    {
        \Illuminate\Support\Facades\Log::info($session);
        if ((int) $session->user_id !== (int) $request->user()->id) {
            abort(403, 'No autorizado');
        }
    }

}
