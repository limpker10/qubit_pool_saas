<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller as BaseController;
// Si quisieras forzar guards/middlewares comunes a todos los tenant controllers, hazlo aquí.

abstract class Controller extends BaseController
{
    public function __construct()
    {
        // Si YA inicializas tenancy en las rutas, no repitas esto.
        // $this->middleware(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
        // $this->middleware(\Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class);

        // Auth/roles por tenant (si usas Sanctum para APIs del tenant):
        // $this->middleware('auth:sanctum');
    }
}
