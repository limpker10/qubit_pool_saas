<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            $centralDomains = config('tenancy.central_domains', []);

            // Rutas del SISTEMA (dominios centrales)
            foreach ($centralDomains as $centralDomain) {
                // web central
                Route::middleware('web')
                    ->domain($centralDomain)
                    ->group(base_path('routes/web.php'));

                // api central
                Route::middleware('api')
                    ->domain($centralDomain)
                    ->prefix('api')
                    ->group(base_path('routes/api.php'));
            }

            // Rutas WEB del TENANT (fallback SPA)
            Route::middleware(['web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
                ->group(base_path('routes/tenant.php'));

            // Rutas API del TENANT  <-- NECESARIO PARA /api/login
            Route::middleware(['api', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])
                ->prefix('api')
                ->group(base_path('routes/tenant_api.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) =>
                $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
