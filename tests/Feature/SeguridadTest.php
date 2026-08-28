<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Estas dos pruebas no comprueban endpoints concretos: recorren las rutas
 * registradas y las verifican una por una. La diferencia importa. Una prueba
 * escrita a mano cubre lo que existe hoy; estas cubren tambien la ruta que
 * alguien anada dentro de seis meses y a la que se le olvide la policy.
 */
function rutasDeLaApi(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($ruta) => Str::startsWith($ruta->uri(), 'api/v1/'));
}

it('exige token en toda ruta que no sea publica a proposito', function () {
    // La lista es explicita y corta: si alguien anade algo aqui, se ve en el
    // diff y hay que justificarlo.
    $publicas = [
        'api/v1/health',
        'api/v1/auth/register',
        'api/v1/auth/login',
    ];

    $desprotegidas = rutasDeLaApi()
        ->reject(fn ($ruta) => in_array($ruta->uri(), $publicas, true))
        ->reject(fn ($ruta) => in_array('auth:sanctum', $ruta->gatherMiddleware(), true))
        ->map(fn ($ruta) => implode('|', $ruta->methods()).' '.$ruta->uri())
        ->values()
        ->all();

    expect($desprotegidas)->toBe([]);
});

it('ninguna ruta con recurso en la url deja pasar a un extrano', function () {
    $dueno = User::factory()->create();
    $project = Project::factory()->for($dueno, 'owner')->create();
    $task = Task::factory()->for($project)->create();

    $intruso = User::factory()->create();

    $conRecurso = rutasDeLaApi()
        ->filter(fn ($ruta) => Str::contains($ruta->uri(), ['{project}', '{task}']));

    expect($conRecurso)->not->toBeEmpty();

    $fugas = [];

    foreach ($conRecurso as $ruta) {
        foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
            $uri = str_replace(
                ['{project}', '{task}'],
                [$project->id, $task->id],
                $ruta->uri()
            );

            // Un cuerpo que satisface la validacion de cualquiera de los
            // endpoints, para que un 422 no enmascare la falta de un 403.
            $respuesta = $this->actingAs($intruso, 'sanctum')->json($metodo, '/'.$uri, [
                'name' => 'Intento de intrusion',
                'title' => 'Intento de intrusion',
                'status' => 'pending',
            ]);

            if ($respuesta->getStatusCode() < 400) {
                $fugas[] = $metodo.' /'.$uri.' devolvio '.$respuesta->getStatusCode();
            }

            // El guard cachea el usuario resuelto entre peticiones del mismo
            // test; sin esto la segunda iteracion reutilizaria la primera.
            $this->app['auth']->forgetGuards();
        }
    }

    expect($fugas)->toBe([]);
});
