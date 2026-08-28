<?php

use App\Models\Project;
use App\Models\User;

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('exige token en todas las rutas de proyectos', function () {
    $project = Project::factory()->create();

    $this->getJson('/api/v1/projects')->assertUnauthorized();
    $this->postJson('/api/v1/projects', ['name' => 'X'])->assertUnauthorized();
    $this->getJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'X'])->assertUnauthorized();
    $this->deleteJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
});

// ---------------------------------------------------------------------
// Aislamiento por dueno
// ---------------------------------------------------------------------

it('lista los propios y ninguno ajeno', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->for($user, 'owner')->create();
    Project::factory()->count(2)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('responde 403 al ver un proyecto ajeno', function () {
    $user = User::factory()->create();
    $ajeno = Project::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$ajeno->id}")
        ->assertForbidden();
});

it('responde 403 al editar un proyecto ajeno', function () {
    $user = User::factory()->create();
    $ajeno = Project::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/projects/{$ajeno->id}", ['name' => 'Secuestrado'])
        ->assertForbidden();

    expect($ajeno->fresh()->name)->not->toBe('Secuestrado');
});

it('responde 403 al borrar un proyecto ajeno', function () {
    $user = User::factory()->create();
    $ajeno = Project::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/projects/{$ajeno->id}")
        ->assertForbidden();

    $this->assertNotSoftDeleted('projects', ['id' => $ajeno->id]);
});

it('responde 403 y no 422 al editar un proyecto ajeno con datos invalidos', function () {
    $user = User::factory()->create();
    $ajeno = Project::factory()->create();

    // Esta prueba defiende una decision de diseno: la autorizacion vive en
    // UpdateProjectRequest::authorize(), que corre antes de las reglas. Si
    // alguien la moviera al controlador, esto pasaria a devolver 422 y le
    // confirmaria al atacante que el payload es lo unico que le falta
    // corregir para tocar un recurso ajeno.
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/projects/{$ajeno->id}", ['name' => ''])
        ->assertForbidden();
});

it('ignora un user_id enviado en el cuerpo', function () {
    $user = User::factory()->create();
    $otro = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', [
            'name' => 'Intento de suplantacion',
            'user_id' => $otro->id,
        ])->assertCreated();

    // El dueno sale del token. validated() ni siquiera devuelve user_id
    // porque no hay regla para el, y ademas no es rellenable en el modelo.
    $this->assertDatabaseHas('projects', [
        'name' => 'Intento de suplantacion',
        'user_id' => $user->id,
    ]);
});

// ---------------------------------------------------------------------
// CRUD
// ---------------------------------------------------------------------

it('crea un proyecto y devuelve la cabecera Location', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', [
            'name' => 'Rediseno del sitio',
            'description' => 'Landing nueva para el lanzamiento',
        ])
        ->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.name', 'Rediseno del sitio')
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'created_at', 'updated_at']]);

    $this->assertDatabaseHas('projects', [
        'name' => 'Rediseno del sitio',
        'user_id' => $user->id,
    ]);
});

it('usa un uuid como identificador publico', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', ['name' => 'Cualquiera'])
        ->json('data.id');

    expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('exige el nombre al crear', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', ['description' => 'sin nombre'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('actualiza parcialmente sin borrar los campos omitidos', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create([
        'name' => 'Original',
        'description' => 'Descripcion original',
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Renombrado'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renombrado')
        ->assertJsonPath('data.description', 'Descripcion original');
});

it('borra logicamente y el recurso pasa a responder 404', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user, 'owner')->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('projects', ['id' => $project->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertNotFound()
        ->assertExactJson(['message' => 'The requested resource was not found.']);
});

it('no incluye en el listado los proyectos borrados', function () {
    $user = User::factory()->create();
    Project::factory()->for($user, 'owner')->create();
    Project::factory()->for($user, 'owner')->trashed()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ---------------------------------------------------------------------
// Filtros y paginacion
// ---------------------------------------------------------------------

it('filtra por nombre', function () {
    $user = User::factory()->create();
    Project::factory()->for($user, 'owner')->create(['name' => 'Rediseno del sitio']);
    Project::factory()->for($user, 'owner')->create(['name' => 'Mudanza de oficina']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects?search=Mudanza')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mudanza de oficina');
});

it('pagina segun per_page', function () {
    $user = User::factory()->create();
    Project::factory()->count(7)->for($user, 'owner')->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects?per_page=3')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 7)
        ->assertJsonPath('meta.last_page', 3);
});

it('rechaza un per_page por encima del tope', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects?per_page=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

it('rechaza un criterio de orden fuera de la lista blanca', function () {
    $user = User::factory()->create();

    // El valor termina dentro de orderBy(). Los bindings de SQL protegen
    // valores, no identificadores: la lista blanca es la unica defensa.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects?sort=user_id')
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});
