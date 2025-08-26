<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\UnitController;
use App\Http\Controllers\Tenant\WarehouseController;
use App\Http\Controllers\Tenant\PoolTableController;
use App\Http\Controllers\Tenant\TableTypeController;
use App\Http\Controllers\Tenant\KardexController;
use App\Http\Controllers\Tenant\CashSessionController;

// Públicas
Route::post('login',    [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'create']);

// Protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('tables', PoolTableController::class);
    Route::apiResource('table_types', TableTypeController::class);

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('product/{product}/stock', [ProductController::class, 'stock']);
    Route::get('product/search',          [ProductController::class, 'search']);

    Route::get('kardex',  [KardexController::class, 'index']);
    Route::post('kardex', [KardexController::class, 'store']);

    Route::get('cash_sessions/list',             [CashSessionController::class, 'index']);
    Route::get('cash_sessions/{cashSession}',    [CashSessionController::class, 'show']);
    Route::get('cash_sessions/current',          [CashSessionController::class, 'current']);
    Route::post('cash_sessions/open',            [CashSessionController::class, 'open']);
    Route::post('cash_sessions/{session}/close', [CashSessionController::class, 'close']);
    Route::get('cash_sessions/{session}/movements',  [CashSessionController::class, 'movements']);
    Route::post('cash_sessions/{session}/movements', [CashSessionController::class, 'addMovement']);

    Route::post('tables/{table}/start',  [PoolTableController::class, 'start']);
    Route::post('tables/{table}/pause',  [PoolTableController::class, 'pause']);
    Route::post('tables/{table}/resume', [PoolTableController::class, 'resume']);
    Route::post('tables/{table}/finish', [PoolTableController::class, 'finish']);
    Route::post('tables/{table}/cancel', [PoolTableController::class, 'cancel']);
});
