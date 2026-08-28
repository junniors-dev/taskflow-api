<?php

it('reporta que la aplicacion y la base de datos responden', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'database' => 'up'])
        ->assertJsonStructure(['status', 'database', 'time']);
});

it('responde una ruta inexistente en json y sin traza', function () {
    // assertExactJson es deliberado: comprueba que la respuesta trae el
    // mensaje y nada mas. Por defecto, con APP_DEBUG activo, Laravel adjunta
    // la traza completa con rutas absolutas del servidor.
    $this->getJson('/api/v1/no-existe')
        ->assertNotFound()
        ->assertExactJson(['message' => 'The requested endpoint does not exist.']);
});

it('rechaza un metodo http no soportado', function () {
    $this->deleteJson('/api/v1/health')
        ->assertStatus(405)
        ->assertExactJson(['message' => 'The HTTP method is not supported for this endpoint.']);
});
