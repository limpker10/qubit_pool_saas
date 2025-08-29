<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Tenant;          // wrapper de stancl/tenancy
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Traits\ApiResponse;

class ClientsController extends Controller
{
    use ApiResponse;

    protected string $baseDomain;

    public function __construct()
    {
        // Configurable por .env TENANCY_BASE_DOMAIN=innovaservicios.pe
        $this->baseDomain = config('tenancy.base_domain', env('TENANCY_BASE_DOMAIN', 'saas-app.test'));
    }

    private function fqdn(string $subdomain): string
    {
        return strtolower($subdomain) . '.' . $this->baseDomain;
    }

    /**
     * GET /clients
     */
    public function index(Request $request)
    {
        $q = Client::query()
            ->with(['plan', 'primaryDomain', 'primaryContact', 'modules']);

        if ($search = $request->string('q')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('company', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhereHas('contacts', function ($c) use ($search) {
                        $c->where('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('plan_id')) {
            $q->where('plan_id', $request->integer('plan_id'));
        }

        if ($request->has('active')) {
            $q->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($q->paginate($perPage));
    }

    /**
     * POST /clients
     * Crea Client (central) + Domain + Contact + módulos,
     * y luego provisiona Tenant + Domain(tenancy) + User admin en la misma request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Client (central)
            'document_type'   => ['required', Rule::in(['DNI', 'RUC'])],
            'document_number' => [
                'required', 'string', 'max:15',
                Rule::unique('clients')->where(fn($q) => $q->where('document_type', $request->input('document_type')))
            ],
            'company'         => ['required', 'string', 'max:255'],
            'is_active'       => ['sometimes', 'boolean'],

            // Plan (opcional)
            'plan_id'         => ['nullable', 'integer', 'exists:plans,id'],
            'plan_code'       => ['nullable', 'string', Rule::exists('plans', 'code')],

            // Subdominio (central + tenancy)
            'subdomain'       => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:63'],

            // Contacto/admin primario
            'email'           => ['required', 'email', 'max:255'],
            'contact_name'    => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:40'],

            // Módulos (opcional)
            'module_ids'      => ['nullable', 'array'],
            'module_ids.*'    => ['integer', 'exists:modules,id'],
            'module_keys'     => ['nullable', 'array'],
            'module_keys.*'   => ['string', 'exists:modules,key'],

            // Admin del tenant
            'admin_password'  => ['nullable', 'string', 'min:8'],
        ]);

        // Validación de longitud del documento
        $len = strlen($validated['document_number']);
        if ($validated['document_type'] === 'DNI' && $len !== 8) {
            return response()->json(['message' => 'El DNI debe tener 8 dígitos.'], 422);
        }
        if ($validated['document_type'] === 'RUC' && $len !== 11) {
            return response()->json(['message' => 'El RUC debe tener 11 dígitos.'], 422);
        }

        $fqdn = $this->fqdn($validated['subdomain']);

        // Unicidad del FQDN en tabla central
        if (ClientDomain::where('fqdn', $fqdn)->exists()) {
            return response()->json(['message' => 'El subdominio ya está en uso.'], 422);
        }

        // Resolver Plan
        $planId = $validated['plan_id'] ?? null;
        if (!$planId && !empty($validated['plan_code'])) {
            $planId = Plan::where('code', $validated['plan_code'])->value('id');
        }

        // Resolver módulos
        $moduleIds = $validated['module_ids'] ?? [];
        if (!empty($validated['module_keys'])) {
            $idsFromKeys = Module::whereIn('key', $validated['module_keys'])->pluck('id')->all();
            $moduleIds = array_values(array_unique(array_merge($moduleIds, $idsFromKeys)));
        }
        $syncData = collect($moduleIds)->mapWithKeys(fn ($id) => [$id => ['enabled' => true]])->all();

        // Password admin (si no llega, generamos una)
        $adminPassword = $validated['admin_password'] ?? Str::random(12);

        // 1) Crear Client + relaciones (CENTRAL)
        $client = \DB::transaction(function () use ($validated, $planId, $fqdn, $syncData) {
            $client = Client::create([
                'uuid'            => (string) Str::uuid(),
                'document_type'   => $validated['document_type'],
                'document_number' => $validated['document_number'],
                'company'         => $validated['company'],
                'plan_id'         => $planId,
                'is_active'       => $validated['is_active'] ?? true,
                'onboarded_at'    => now(),
            ]);

            // Dominio primario (central)
            $client->domains()->create([
                'fqdn'       => $fqdn,
                'is_primary' => true,
            ]);

            // Contacto primario (central)
            $client->contacts()->create([
                'name'       => $validated['contact_name'] ?? null,
                'email'      => $validated['email'],
                'phone'      => $validated['phone'] ?? null,
                'is_primary' => true,
            ]);

            // Módulos (central)
            if (!empty($syncData)) {
                $client->modules()->sync($syncData);
            }

            return $client;
        });

        // 2) Provisionar TENANT (sin jobs)
        try {
            // ID sugerido para el tenant
            $tenantId = 'tenant_' . Str::slug($client->document_number, '_');

            // Verificación preventiva del dominio en tabla tenancy (por si acaso)
            if (\Stancl\Tenancy\Database\Models\Domain::query()->where('domain', $fqdn)->exists()) {
                // Limpieza central si choca
                $client->delete();
                return response()->json(['message' => 'El subdominio ya está en uso en tenancy.'], 422);
            }

            // Crear tenant central (tenancy)
            /** @var \App\Models\Tenant $tenant */
            $tenant = Tenant::create([
                'id'   => $tenantId,
                'data' => ['client_id' => $client->id],
            ]);

            // Dominio tenancy
            $tenant->domains()->create(['domain' => $fqdn]);

            // Migraciones + crear admin dentro del tenant
            $tenant->run(function () use ($validated, $adminPassword) {
                // Migrar BD del tenant
                Artisan::call('migrate', ['--force' => true]);

                // Crear usuario admin
                $userModel = config('auth.providers.users.model', \App\Models\User::class);

                /** @var \Illuminate\Database\Eloquent\Model $admin */
                $admin = (new $userModel)::create([
                    'name'              => $validated['contact_name'] ?? 'Administrador',
                    'email'             => $validated['email'],
                    'password'          => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                    'warehouse_id'      => 1,
                ]);

                // Si usas Spatie:
                // $admin->assignRole('admin');
            });

        } catch (\Throwable $e) {
            // Limpieza si falla el tenant
            try {
                if (isset($tenant)) {
                    $tenant->domains()->delete();
                    $tenant->delete();
                }
            } catch (\Throwable $ignored) {}

            // Borra el cliente central para mantener consistencia
            try { $client->delete(); } catch (\Throwable $ignored) {}

            Log::error('Error al provisionar tenant: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al provisionar el tenant',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // Respuesta
        $client->load(['plan', 'primaryDomain', 'primaryContact', 'modules']);

        return response()->json([
            'success'         => true,
            'message'         => 'Cliente y tenant creados correctamente.',
            'data'            => $client,
            'tenant'          => ['id' => $tenant->id, 'domain' => $fqdn],
            // Sólo devolvemos la contraseña si fue generada aquí
            'admin_password'  => $request->filled('admin_password') ? null : $adminPassword,
        ], 201);
    }

    /**
     * GET /clients/{client}
     */
    public function show(Client $client)
    {
        $client->load(['plan', 'primaryDomain', 'primaryContact', 'modules']);
        return $this->successResponse($client, 'Cliente encontrado');
    }

    /**
     * PUT/PATCH /clients/{client}
     * Permite actualizar: company, doc, plan, subdomain, contacto, módulos.
     * Si cambia subdomain, actualiza también el dominio del tenant.
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'document_type'   => ['sometimes', Rule::in(['DNI', 'RUC'])],
            'document_number' => [
                'sometimes', 'string', 'max:15',
                Rule::unique('clients')->where(function ($q) use ($request, $client) {
                    $type = $request->input('document_type', $client->document_type);
                    return $q->where('document_type', $type);
                })->ignore($client->id)
            ],
            'company'         => ['sometimes', 'string', 'max:255'],
            'is_active'       => ['sometimes', 'boolean'],

            'plan_id'         => ['nullable', 'integer', 'exists:plans,id'],
            'plan_code'       => ['nullable', 'string', Rule::exists('plans','code')],

            'subdomain'       => ['sometimes', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:63'],

            'email'           => ['sometimes', 'email', 'max:255'],
            'contact_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:40'],

            'module_ids'      => ['nullable', 'array'],
            'module_ids.*'    => ['integer', 'exists:modules,id'],
            'module_keys'     => ['nullable', 'array'],
            'module_keys.*'   => ['string', 'exists:modules,key'],
        ]);

        // Validar longitud si cambia documento
        if ($request->filled('document_type') || $request->filled('document_number')) {
            $type = $request->input('document_type', $client->document_type);
            $num  = $request->input('document_number', $client->document_number);
            $len  = strlen($num);
            if ($type === 'DNI' && $len !== 8) {
                return response()->json(['message' => 'El DNI debe tener 8 dígitos.'], 422);
            }
            if ($type === 'RUC' && $len !== 11) {
                return response()->json(['message' => 'El RUC debe tener 11 dígitos.'], 422);
            }
        }

        // Resolver plan
        $planId = $validated['plan_id'] ?? null;
        if (!$planId && !empty($validated['plan_code'])) {
            $planId = Plan::where('code', $validated['plan_code'])->value('id');
        }

        // Resolver módulos
        $moduleIds = $validated['module_ids'] ?? null;
        if (!is_null($validated['module_keys'] ?? null)) {
            $idsFromKeys = Module::whereIn('key', $validated['module_keys'])->pluck('id')->all();
            $moduleIds = is_array($moduleIds)
                ? array_values(array_unique(array_merge($moduleIds, $idsFromKeys)))
                : $idsFromKeys;
        }
        $syncData = is_array($moduleIds)
            ? collect($moduleIds)->mapWithKeys(fn($id) => [$id => ['enabled' => true]])->all()
            : null;

        // Nuevo FQDN si cambia subdominio (verificar unicidad en central)
        $newFqdn = null;
        if ($request->filled('subdomain')) {
            $newFqdn = $this->fqdn($validated['subdomain']);
            $primary = $client->primaryDomain;
            $existsCentral = ClientDomain::where('fqdn', $newFqdn)
                ->when($primary, fn($q) => $q->where('id', '!=', $primary->id))
                ->exists();

            if ($existsCentral) {
                return response()->json(['message' => 'El subdominio ya está en uso.'], 422);
            }
        }

        // Actualización central + tenancy (si aplica) en dos pasos
        \DB::transaction(function () use ($client, $validated, $planId, $syncData, $newFqdn, $request) {
            // Actualiza base
            $client->fill([
                'document_type'   => $validated['document_type']   ?? $client->document_type,
                'document_number' => $validated['document_number'] ?? $client->document_number,
                'company'         => $validated['company']         ?? $client->company,
                'is_active'       => $validated['is_active']       ?? $client->is_active,
            ]);

            if (!is_null($planId)) {
                $client->plan_id = $planId;
            }
            $client->save();

            // Actualiza dominio central
            if (!is_null($newFqdn)) {
                $client->primaryDomain()
                    ->updateOrCreate([], ['fqdn' => $newFqdn, 'is_primary' => true]);
            }

            // Actualiza contacto primario si vino info
            if ($request->hasAny(['email', 'contact_name', 'phone'])) {
                $client->primaryContact()
                    ->updateOrCreate([], [
                        'name'       => $validated['contact_name'] ?? optional($client->primaryContact)->name,
                        'email'      => $validated['email']        ?? optional($client->primaryContact)->email,
                        'phone'      => $validated['phone']        ?? optional($client->primaryContact)->phone,
                        'is_primary' => true,
                    ]);
            }

            // Sincroniza módulos si llegaron
            if (!is_null($syncData)) {
                $client->modules()->sync($syncData);
            }
        });

        // Si cambió subdominio, actualiza dominio en tenancy
        if (!is_null($newFqdn)) {
            // Buscar tenant por data->client_id
            $tenant = Tenant::query()->where('data->client_id', $client->id)->first();
            if ($tenant) {
                // Verificar unicidad en tenancy
                $domainsModel = \Stancl\Tenancy\Database\Models\Domain::query();
                $existsTenancy = $domainsModel->where('domain', $newFqdn)->where('tenant_id', '!=', $tenant->id)->exists();
                if ($existsTenancy) {
                    return response()->json(['message' => 'El subdominio ya está en uso en tenancy.'], 422);
                }

                // Actualizar el primer dominio del tenant (o crearlo si no existe)
                $domain = $tenant->domains()->first();
                if ($domain) {
                    $domain->update(['domain' => $newFqdn]);
                } else {
                    $tenant->domains()->create(['domain' => $newFqdn]);
                }
            }
        }

        $client->load(['plan', 'primaryDomain', 'primaryContact', 'modules']);
        return $this->successResponse($client, 'Cliente actualizado correctamente');
    }

    /**
     * DELETE /clients/{client}
     * Elimina Client central (soft delete) y borra Tenant + Domains en tenancy.
     */
    public function destroy(Client $client)
    {
        // Buscar tenant por data->client_id
        try {
            $tenant = Tenant::query()->where('data->client_id', $client->id)->first();
            if ($tenant) {
                $tenant->domains()->delete();
                $tenant->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo borrar el tenant al eliminar el cliente: '.$e->getMessage());
        }

        $client->delete(); // SoftDeletes; domains/contacts/pivot caen por cascade
        return $this->successResponse(null, 'Cliente eliminado correctamente');
    }
}
