<?php

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
    } catch (\Throwable) {
        $database = 'down';
    }

    return response()->json([
        'status' => $database === 'up' ? 'ok' : 'degraded',
        'database' => $database,
        'time' => now()->toIso8601String(),
    ], $database === 'up' ? 200 : 503);
});
