<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'create']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('clients', ClientsController::class);
    // plans
    Route::get   ('plans',                [ServiceController::class, 'plansIndex']);
    Route::post  ('plans',                [ServiceController::class, 'plansStore']);
    Route::get   ('plans/{plan}',         [ServiceController::class, 'plansShow']);
    Route::put   ('plans/{plan}',         [ServiceController::class, 'plansUpdate']);
    Route::patch ('plans/{plan}/toggle',  [ServiceController::class, 'plansToggle']);
    Route::delete('plans/{plan}',         [ServiceController::class, 'plansDestroy']);

    // modules
    Route::get   ('modules',                 [ServiceController::class, 'modulesIndex']);
    Route::post  ('modules',                 [ServiceController::class, 'modulesStore']);
    Route::get   ('modules/{module}',        [ServiceController::class, 'modulesShow']);
    Route::put   ('modules/{module}',        [ServiceController::class, 'modulesUpdate']);
    Route::patch ('modules/{module}/toggle', [ServiceController::class, 'modulesToggle']);
    Route::delete('modules/{module}',        [ServiceController::class, 'modulesDestroy']);

    // traer ambos catálogos de una (para poblar selects)
});

