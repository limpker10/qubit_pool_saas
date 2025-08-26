<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardKpiController extends Controller
{
    /**
     * GET /api/dashboard/kpis
     *
     * Query params opcionales:
     * - range: today|yesterday|7d|30d|month|custom (default: today)
     * - from, to: fechas ISO cuando range=custom (ej. 2025-08-01 ... 2025-08-21)
     * - session_id: filtra por una sesión de caja específica (para KPIs de cash y ventas ligadas)
     * - currency: PEN|USD (si manejas multi-moneda; aquí solo filtra)
     */
    public function index(Request $request)
    {
        // Timezone de negocio (Perú)
        $tz = 'America/Lima';

        // -------- 1) Resolver rango de fechas --------
        [$from, $to] = $this->resolveDateRange(
            $request->string('range', 'today')->toString(),
            $request->input('from'),
            $request->input('to'),
            $tz
        );

        $sessionId = $request->input('session_id');
        $currency  = $request->input('currency'); // opcional

        // Base WHERE para documentos: emitidos dentro del rango, no anulados
        $docsQuery = DB::table('documents')
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$from, $to]);

        if ($currency) {
            $docsQuery->where('currency', $currency);
        }
        if ($sessionId) {
            $docsQuery->where('cash_session_id', $sessionId);
        }

        // -------- 2) Totales básicos --------
        $totals = $docsQuery->clone()
            ->selectRaw('
                COALESCE(SUM(total), 0) as total_sales,
                COUNT(*) as documents_count
            ')
            ->first();

        $totalSales     = (float) ($totals->total_sales ?? 0);
        $documentsCount = (int)   ($totals->documents_count ?? 0);
        $avgTicket      = $documentsCount > 0 ? round($totalSales / $documentsCount, 2) : 0;

        // -------- 3) Ventas por método de pago --------
        $byPaymentMethod = $docsQuery->clone()
            ->select('payment_method', DB::raw('COALESCE(SUM(total),0) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        // -------- 4) Ventas por tipo de documento --------
        $byDocumentType = $docsQuery->clone()
            ->select('type', DB::raw('COALESCE(SUM(total),0) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        // -------- 5) Serie temporal por día (sales_over_time) --------
        // Usamos DATE(issue_date) para agrupar; si tu motor es MySQL/MariaDB está ok
        $salesOverTime = $docsQuery->clone()
            ->selectRaw('DATE(issue_date) as day, COALESCE(SUM(total),0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // Normalizar para incluir días sin ventas (opcional)
        $series = $this->fillDateSeries($salesOverTime, $from, $to, $tz);

        // -------- 6) Top items (desde documents_details) --------
        $detailsQuery = DB::table('documents_details as dd')
            ->join('documents as d', 'd.id', '=', 'dd.document_id')
            ->where('d.status', 'issued')
            ->whereBetween('d.issue_date', [$from, $to]);

        if ($currency) {
            $detailsQuery->where('d.currency', $currency);
        }
        if ($sessionId) {
            $detailsQuery->where('d.cash_session_id', $sessionId);
        }

        $topItems = $detailsQuery
            ->selectRaw('
                dd.description,
                COALESCE(SUM(dd.quantity),0) as total_qty,
                COALESCE(SUM(dd.line_total),0) as total_amount
            ')
            ->groupBy('dd.description')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        // -------- 7) Caja (cash) --------
        $cash = $this->computeCashKpis($sessionId, $from, $to, $tz);

        return response()->json([
            'filters' => [
                'from'      => $from->toIso8601String(),
                'to'        => $to->toIso8601String(),
                'range'     => $request->string('range', 'today')->toString(),
                'sessionId' => $sessionId,
                'currency'  => $currency,
            ],
            'totals' => [
                'total_sales'     => round($totalSales, 2),
                'documents_count' => $documentsCount,
                'avg_ticket'      => $avgTicket,
            ],
            'by_payment_method' => $byPaymentMethod,
            'by_document_type'  => $byDocumentType,
            'sales_over_time'   => $series,          // [{day: 'YYYY-MM-DD', total: 0}, ...]
            'top_items'         => $topItems,        // [{description, total_qty, total_amount}]
            'cash'              => $cash,            // info de la caja
        ]);
    }

    // ================== Helpers ==================

    private function resolveDateRange(string $range, $from, $to, string $tz): array
    {
        $now  = Carbon::now($tz);
        $start = null;
        $end   = null;

        switch ($range) {
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end   = $now->copy()->subDay()->endOfDay();
                break;
            case '7d':
                $start = $now->copy()->subDays(6)->startOfDay(); // incluye hoy
                $end   = $now->copy()->endOfDay();
                break;
            case '30d':
                $start = $now->copy()->subDays(29)->startOfDay();
                $end   = $now->copy()->endOfDay();
                break;
            case 'month':
                $start = $now->copy()->startOfMonth();
                $end   = $now->copy()->endOfMonth();
                break;
            case 'custom':
                $start = Carbon::parse($from, $tz)->startOfDay();
                $end   = Carbon::parse($to, $tz)->endOfDay();
                break;
            case 'today':
            default:
                $start = $now->copy()->startOfDay();
                $end   = $now->copy()->endOfDay();
                break;
        }
        return [$start, $end];
    }

    /** Rellena días vacíos con 0 para que la serie temporal sea continua */
    private function fillDateSeries($rows, Carbon $from, Carbon $to, string $tz)
    {
        $map = [];
        foreach ($rows as $r) {
            $map[$r->day] = (float) $r->total;
        }

        $cursor = $from->copy()->startOfDay();
        $out = [];
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $out[] = [
                'day'   => $key,
                'total' => isset($map[$key]) ? round($map[$key], 2) : 0.0,
            ];
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * KPIs de caja:
     * - Si session_id viene: usa esa sesión
     * - Si no viene: busca la sesión abierta del usuario autenticado (o la más reciente abierta)
     * - Devuelve: estado, apertura, movimientos dentro del rango, esperado, contado, diferencia
     */
    private function computeCashKpis($sessionId, Carbon $from, Carbon $to, string $tz): array
    {
        // 1) Resolver sesión
        $session = null;

        if ($sessionId) {
            $session = DB::table('cash_sessions')->where('id', $sessionId)->first();
        } else {
            // Traer la más reciente en estado open (global o por usuario si prefieres)
            $session = DB::table('cash_sessions')
                ->where('status', 'open')
                ->orderByDesc('opened_at')
                ->first();
        }

        if (!$session) {
            return [
                'has_session' => false,
                'message'     => 'No hay sesión de caja abierta ni seleccionada.',
            ];
        }

        // 2) Movimientos de la sesión (excluyendo el tipo 'open' porque ya está en opening_cash)
        $movs = DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->whereBetween('created_at', [$from, $to])
            ->where('type', '!=', 'open')
            ->select('type', DB::raw('COALESCE(SUM(amount),0) as total'))
            ->groupBy('type')
            ->get();

        $sumMovements = (float) DB::table('cash_movements')
            ->where('cash_session_id', $session->id)
            ->whereBetween('created_at', [$from, $to])
            ->where('type', '!=', 'open')
            ->sum('amount'); // en tu modelo: + entra, - sale

        $expected = round(((float)$session->opening_cash) + $sumMovements, 2);

        return [
            'has_session'    => true,
            'session' => [
                'id'            => $session->id,
                'status'        => $session->status,
                'opened_at'     => Carbon::parse($session->opened_at, $tz)->toIso8601String(),
                'closed_at'     => $session->closed_at ? Carbon::parse($session->closed_at, $tz)->toIso8601String() : null,
                'opening_cash'  => (float) $session->opening_cash,
                'counted_cash'  => $session->counted_cash !== null ? (float) $session->counted_cash : null,
                'expected_cash' => $expected,
                'difference'    => $session->counted_cash !== null ? round(((float)$session->counted_cash) - $expected, 2) : null,
            ],
            'movements_breakdown' => $movs, // [{type, total}]
        ];
    }
}
