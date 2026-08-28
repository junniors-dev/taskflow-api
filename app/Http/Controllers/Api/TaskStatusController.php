<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;

/**
 * Cambiar el estado de una tarea tiene su propio endpoint en vez de ser un
 * PATCH generico: es el gesto mas frecuente de un tablero (arrastrar una
 * tarjeta de una columna a otra) y merece una ruta que diga lo que hace.
 * Ademas le da su propio limite de validacion, sin arrastrar el resto de
 * campos editables.
 */
class TaskStatusController extends Controller
{
    public function __invoke(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        $task->update(['status' => $request->validated('status')]);

        return TaskResource::make($task->load('assignee'));
    }
}
