<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lista las tareas sin una consulta por cada asignado', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    User::factory()->count(5)->create()->each(
        fn (User $asignado) => Task::factory()->for($project)->create(['assigned_to' => $asignado->id])
    );

    DB::enableQueryLog();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertOk()
        ->assertJsonCount(5, 'data');

    $consultasAUsuarios = collect(DB::getQueryLog())
        ->filter(fn (array $q) => str_contains($q['query'], 'from `users`'))
        ->count();

    // Con carga anticipada los cinco asignados salen en una sola consulta.
    // Sin ella serian cinco, una por tarea. El margen cubre la consulta que
    // resuelve al propio usuario autenticado.
    expect($consultasAUsuarios)->toBeLessThanOrEqual(2);
});

it('rechaza con 400 un cuerpo que dice ser json y no lo es', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->call(
        'POST',
        '/api/v1/projects',
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        '{"name": "sin cerrar'
    );

    // Antes del middleware, Laravel trataba el cuerpo roto como uno vacio y
    // esto devolvia "el campo name es obligatorio": un mensaje que manda al
    // cliente a buscar un campo que si habia enviado.
    expect($response->getStatusCode())->toBe(400)
        ->and($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->json('message'))->toBe('The request body is not valid JSON.');
});

it('deja pasar un cuerpo json valido', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', ['name' => 'Con el cuerpo bien formado'])
        ->assertCreated();
});

it('deja pasar una peticion sin cuerpo', function () {
    $user = User::factory()->create();

    // GET y DELETE no llevan cuerpo: el middleware no debe estorbarles.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects')
        ->assertOk();
});
