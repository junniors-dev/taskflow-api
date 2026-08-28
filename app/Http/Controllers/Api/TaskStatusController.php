<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use OpenApi\Attributes as OA;

/**
 * Cambiar el estado de una tarea tiene su propio endpoint en vez de ser un
 * PATCH generico: es el gesto mas frecuente de un tablero (arrastrar una
 * tarjeta de una columna a otra) y merece una ruta que diga lo que hace.
 * Ademas le da su propio limite de validacion, sin arrastrar el resto de
 * campos editables.
 */
class TaskStatusController extends Controller
{
    #[OA\Patch(
        path: '/tasks/{task}/status',
        operationId: 'tasks.status',
        summary: 'Cambia el estado de una tarea',
        description: 'Endpoint dedicado al gesto de mover una tarjeta entre columnas. Acepta solo el campo "status".',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed'], example: 'in_progress'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado actualizado',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Tarea de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Estado fuera del enum', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function __invoke(UpdateTaskStatusRequest $request, Task $task): TaskResource
    {
        $task->update(['status' => $request->validated('status')]);

        return TaskResource::make($task->load('assignee'));
    }
}
