<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API (v1)
|--------------------------------------------------------------------------
| El prefijo /api/v1 se declara en bootstrap/app.php, no aqui.
*/

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $database = 'up';
    } catch (Throwable) {
        $database = 'down';
    }

    return response()->json([
        'status' => $database === 'up' ? 'ok' : 'degraded',
        'database' => $database,
        'time' => now()->toIso8601String(),
    ], $database === 'up' ? 200 : 503);
})->name('health');

Route::prefix('auth')->name('auth.')->group(function () {
    // Limite estricto: estos dos endpoints son el objetivo natural de un
    // ataque de fuerza bruta.
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });
});
