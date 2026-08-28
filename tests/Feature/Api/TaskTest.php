<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Crea un usuario con un proyecto propio.
 *
 * @return array{0: User, 1: Project}
 */
function conProyecto(): array
{
    $user = User::factory()->create();

    return [$user, Project::factory()->for($user, 'owner')->create()];
}

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

it('exige token en las rutas anidadas y en las aplanadas', function () {
    $task = Task::factory()->create();

    $this->getJson("/api/v1/projects/{$task->project_id}/tasks")->assertUnauthorized();
    $this->postJson("/api/v1/projects/{$task->project_id}/tasks", ['title' => 'X'])->assertUnauthorized();
    $this->getJson("/api/v1/tasks/{$task->id}")->assertUnauthorized();
    $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'X'])->assertUnauthorized();
    $this->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])->assertUnauthorized();
    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertUnauthorized();
});

// ---------------------------------------------------------------------
// Aislamiento heredado del proyecto
// ---------------------------------------------------------------------

it('responde 403 al listar las tareas de un proyecto ajeno', function () {
    $user = User::factory()->create();
    $ajeno = Project::factory()->create();
    Task::factory()->count(2)->for($ajeno)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$ajeno->id}/tasks")
        ->assertForbidden();
});

it('responde 403 al crear una tarea en un proyecto ajeno', function () {
    $user = User::factory()->create();
    $ajeno = Project::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$ajeno->id}/tasks", ['title' => 'Colada'])
        ->assertForbidden();

    $this->assertDatabaseMissing('tasks', ['title' => 'Colada']);
});

it('responde 403 al ver, editar o borrar una tarea ajena por la ruta aplanada', function () {
    $user = User::factory()->create();
    $ajena = Task::factory()->create();

    // La ruta aplanada no menciona el proyecto, asi que es justo donde seria
    // facil olvidarse de comprobar el dueno. TaskPolicy lo hereda del padre.
    $this->actingAs($user, 'sanctum')->getJson("/api/v1/tasks/{$ajena->id}")->assertForbidden();
    $this->actingAs($user, 'sanctum')->patchJson("/api/v1/tasks/{$ajena->id}", ['title' => 'Robada'])->assertForbidden();
    $this->actingAs($user, 'sanctum')->patchJson("/api/v1/tasks/{$ajena->id}/status", ['status' => 'completed'])->assertForbidden();
    $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/tasks/{$ajena->id}")->assertForbidden();

    $this->assertNotSoftDeleted('tasks', ['id' => $ajena->id]);
});

// ---------------------------------------------------------------------
// CRUD
// ---------------------------------------------------------------------

it('crea una tarea dentro del proyecto de la ruta', function () {
    [$user, $project] = conProyecto();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Disenar el logo',
            'description' => 'Tres propuestas',
            'due_date' => '2026-09-15',
        ])
        ->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.title', 'Disenar el logo')
        ->assertJsonPath('data.due_date', '2026-09-15')
        ->assertJsonStructure(['data' => ['id', 'title', 'status', 'due_date', 'assigned_to', 'created_at']]);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Disenar el logo',
        'project_id' => $project->id,
    ]);
});

it('nace pendiente si no se indica el estado', function () {
    [$user, $project] = conProyecto();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Sin estado'])
        ->assertCreated()
        ->assertJsonPath('data.status', TaskStatus::Pending->value);
});

it('exige el titulo', function () {
    [$user, $project] = conProyecto();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", ['description' => 'sin titulo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('rechaza un estado que no existe en el enum', function () {
    [$user, $project] = conProyecto();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Con estado invalido',
            'status' => 'terminada',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('rechaza asignar la tarea a un usuario inexistente', function () {
    [$user, $project] = conProyecto();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Asignada al vacio',
            'assigned_to' => 999999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assigned_to');
});

it('muestra una tarea propia con su proyecto y su asignado', function () {
    [$user, $project] = conProyecto();
    $asignado = User::factory()->create();
    $task = Task::factory()->for($project)->create(['assigned_to' => $asignado->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.assignee.id', $asignado->id)
        ->assertJsonPath('data.project.id', $project->id);
});

it('actualiza parcialmente sin borrar los campos omitidos', function () {
    [$user, $project] = conProyecto();
    $task = Task::factory()->for($project)->create([
        'title' => 'Original',
        'description' => 'Descripcion original',
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Renombrada'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Renombrada')
        ->assertJsonPath('data.description', 'Descripcion original');
});

it('cambia el estado por su endpoint dedicado', function () {
    [$user, $project] = conProyecto();
    $task = Task::factory()->for($project)->status(TaskStatus::Pending)->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('rechaza un estado invalido en el endpoint dedicado', function () {
    [$user, $project] = conProyecto();
    $task = Task::factory()->for($project)->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/tasks/{$task->id}/status", ['status' => 'archivada'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('borra logicamente y la tarea pasa a responder 404', function () {
    [$user, $project] = conProyecto();
    $task = Task::factory()->for($project)->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/tasks/{$task->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertNotFound();
});

// ---------------------------------------------------------------------
// ADR-04: propagacion del borrado logico
// ---------------------------------------------------------------------

it('al borrar un proyecto sus tareas dejan de responder', function () {
    [$user, $project] = conProyecto();
    $task = Task::factory()->for($project)->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    // El ON DELETE CASCADE de MySQL no se entera de un borrado logico: la
    // propagacion la hace el evento deleting del modelo Project. Sin ella,
    // esta tarea seguiria respondiendo 200 en su ruta aplanada, huerfana de
    // un proyecto que ya no existe.
    $this->assertSoftDeleted('tasks', ['id' => $task->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertNotFound();
});

// ---------------------------------------------------------------------
// Listado y filtros
// ---------------------------------------------------------------------

it('lista solo las tareas del proyecto pedido', function () {
    [$user, $project] = conProyecto();
    $otro = Project::factory()->for($user, 'owner')->create();

    Task::factory()->count(3)->for($project)->create();
    Task::factory()->count(2)->for($otro)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('filtra por estado', function () {
    [$user, $project] = conProyecto();
    Task::factory()->for($project)->status(TaskStatus::Pending)->create();
    Task::factory()->count(2)->for($project)->status(TaskStatus::Completed)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks?status=completed")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filtra por fecha limite', function () {
    [$user, $project] = conProyecto();
    Task::factory()->for($project)->dueOn('2026-09-01')->create();
    Task::factory()->for($project)->dueOn('2026-12-01')->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks?due_before=2026-10-01")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filtra por usuario asignado', function () {
    [$user, $project] = conProyecto();
    $asignado = User::factory()->create();
    Task::factory()->count(2)->for($project)->create(['assigned_to' => $asignado->id]);
    Task::factory()->for($project)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks?assigned_to={$asignado->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('rechaza un filtro de estado fuera del enum', function () {
    [$user, $project] = conProyecto();

    // Devolver 422 y no una lista vacia: si no, el cliente leeria un error
    // de escritura como "este proyecto no tiene tareas terminadas".
    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks?status=terminada")
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('rechaza un per_page por encima del tope', function () {
    [$user, $project] = conProyecto();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}/tasks?per_page=101")
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

// ---------------------------------------------------------------------
// Conteo en el proyecto
// ---------------------------------------------------------------------

it('incluye el numero de tareas al ver un proyecto', function () {
    [$user, $project] = conProyecto();
    Task::factory()->count(4)->for($project)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.tasks_count', 4);
});
