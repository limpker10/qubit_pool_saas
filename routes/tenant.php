<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Controllers\TenantAssetsController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // 1) Assets del tenant (debe ir primero)
    Route::get('/assets/{path}', [TenantAssetsController::class, 'asset'])
        ->where('path', '.*')
        ->name('tenant.asset');

    Route::view('/{any}', 'tenant')->where('any', '^(?!api).*$');

});

