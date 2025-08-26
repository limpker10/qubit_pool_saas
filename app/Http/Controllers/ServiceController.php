<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    // ==========================
    // PLANES
    // ==========================

    /**
     * GET /catalog/plans
     * Query params:
     * - q: string (busca por code/name)
     * - active: bool (1/0)
     * - per_page: int (por defecto 15) | si envías all=1 devuelve todo
     */
    public function plansIndex(Request $request)
    {
        $q = Plan::query();

        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $q->where('is_active', $active);
        }

        if ($request->boolean('all')) {
            return response()->json($q->orderBy('name')->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->orderBy('name')->paginate($perPage));
    }

    /**
     * POST /catalog/plans
     */
    public function plansStore(Request $request)
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'alpha_dash', 'unique:plans,code'],
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $plan = Plan::create([
            'code'        => strtoupper($validated['code']),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json($plan, 201);
    }

    /**
     * GET /catalog/plans/{plan}
     */
    public function plansShow(Plan $plan)
    {
        return response()->json($plan);
    }

    /**
     * PUT /catalog/plans/{plan}
     */
    public function plansUpdate(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'code'        => ['sometimes', 'string', 'max:50', 'alpha_dash', Rule::unique('plans', 'code')->ignore($plan->id)],
            'name'        => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $plan->fill($validated)->save();

        return response()->json($plan->fresh());
    }

    /**
     * PATCH /catalog/plans/{plan}/toggle
     */
    public function plansToggle(Plan $plan)
    {
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        return response()->json($plan->fresh());
    }

    /**
     * DELETE /catalog/plans/{plan}
     * Bloquea si hay clientes que referencian el plan.
     */
    public function plansDestroy(Plan $plan)
    {
        $inUse = Client::where('plan_id', $plan->id)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar: el plan está asignado a uno o más clientes.',
            ], 409);
        }

        $plan->delete();
        return response()->json(null, 204);
    }

    // ==========================
    // MÓDULOS
    // ==========================

    /**
     * GET /catalog/modules
     * Query params:
     * - q: string (busca por key/name)
     * - active: bool (1/0)
     * - per_page: int | all=1 para todo
     */
    public function modulesIndex(Request $request)
    {
        $q = Module::query();

        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('key', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $q->where('is_active', $active);
        }

        if ($request->boolean('all')) {
            return response()->json($q->orderBy('name')->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->orderBy('name')->paginate($perPage));
    }

    /**
     * POST /catalog/modules
     */
    public function modulesStore(Request $request)
    {
        $validated = $request->validate([
            'key'         => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', 'unique:modules,key'],
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $module = Module::create([
            'key'         => strtolower($validated['key']),
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json($module, 201);
    }

    /**
     * GET /catalog/modules/{module}
     */
    public function modulesShow(Module $module)
    {
        return response()->json($module);
    }

    /**
     * PUT /catalog/modules/{module}
     */
    public function modulesUpdate(Request $request, Module $module)
    {
        $validated = $request->validate([
            'key'         => ['sometimes', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', Rule::unique('modules', 'key')->ignore($module->id)],
            'name'        => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['key'])) {
            $validated['key'] = strtolower($validated['key']);
        }

        $module->fill($validated)->save();

        return response()->json($module->fresh());
    }

    /**
     * PATCH /catalog/modules/{module}/toggle
     */
    public function modulesToggle(Module $module)
    {
        $module->is_active = ! $module->is_active;
        $module->save();

        return response()->json($module->fresh());
    }

    /**
     * DELETE /catalog/modules/{module}
     * Bloquea si está asignado a algún cliente (pivot client_module).
     */
    public function modulesDestroy(Module $module)
    {
        $inUse = \DB::table('client_module')->where('module_id', $module->id)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar: el módulo está asignado a uno o más clientes.',
            ], 409);
        }

        $module->delete();
        return response()->json(null, 204);
    }

    // ==========================
    // BOOTSTRAP (opcional)
    // ==========================

    /**
     * GET /catalog/bootstrap
     * Devuelve listas activas de planes y módulos para poblar selects/checkboxes.
     */
    public function bootstrap()
    {
        return response()->json([
            'plans'   => Plan::where('is_active', true)->orderBy('name')->get(),
            'modules' => Module::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
