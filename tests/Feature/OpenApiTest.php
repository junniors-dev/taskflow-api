<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * La documentacion que se queda atras del codigo es peor que no tener
 * ninguna: promete un contrato que la API ya no cumple. Esta prueba
 * regenera el documento y comprueba que no falta ninguna ruta.
 */
it('documenta todas las rutas de la api', function () {
    Artisan::call('l5-swagger:generate');

    $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);
    $documentadas = array_keys($spec['paths']);

    $registradas = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => Str::startsWith($uri, 'api/v1/'))
        ->map(fn (string $uri) => '/'.Str::after($uri, 'api/v1/'))
        ->unique()
        ->values()
        ->all();

    expect($registradas)->not->toBeEmpty()
        ->and(array_diff($registradas, $documentadas))->toBe([]);
});

it('declara el esquema de seguridad por token', function () {
    $spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

    expect($spec['components']['securitySchemes']['bearerAuth'])
        ->toMatchArray(['type' => 'http', 'scheme' => 'bearer']);
});
