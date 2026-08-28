<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class TaskController extends Controller
{
    /**
     * Tareas de un proyecto. Ruta anidada: sin el proyecto, la peticion no
     * tendria sentido.
     */
    #[OA\Get(
        path: '/projects/{project}/tasks',
        operationId: 'tasks.index',
        summary: 'Lista las tareas de un proyecto',
        description: 'Ruta anidada porque una tarea no existe fuera de un proyecto. Un filtro con un valor invalido devuelve 422, no una lista vacia.',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed'])),
            new OA\Parameter(name: 'assigned_to', in: 'query', description: 'Id de un usuario existente.', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'due_before', in: 'query', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-10-01')),
            new OA\Parameter(name: 'due_after', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Filtra por titulo.', schema: new OA\Schema(type: 'string', maxLength: 160)),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', default: '-created_at', enum: ['due_date', '-due_date', 'created_at', '-created_at', 'title', '-title', 'status', '-status'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Task')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'El proyecto es de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'El proyecto no existe', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Filtro invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function index(IndexTaskRequest $request, Project $project): AnonymousResourceCollection
    {
        $sort = $request->string('sort', '-created_at')->toString();
        $descending = str_starts_with($sort, '-');

        $tasks = $project->tasks()
            // Sin este with(), pintar veinte tareas con su asignado dispararia
            // veintiuna consultas. preventLazyLoading lo convertiria en
            // excepcion, pero mejor no llegar ahi.
            ->with('assignee')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->when(
                $request->filled('assigned_to'),
                fn ($query) => $query->where('assigned_to', $request->integer('assigned_to'))
            )
            ->when(
                $request->filled('due_before'),
                fn ($query) => $query->whereDate('due_date', '<=', $request->string('due_before'))
            )
            ->when(
                $request->filled('due_after'),
                fn ($query) => $query->whereDate('due_date', '>=', $request->string('due_after'))
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%')
            )
            ->orderBy(ltrim($sort, '-'), $descending ? 'desc' : 'asc')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    #[OA\Post(
        path: '/projects/{project}/tasks',
        operationId: 'tasks.store',
        summary: 'Crea una tarea dentro de un proyecto',
        description: 'El proyecto lo fija la ruta, no el cuerpo: no hay forma de crear una tarea dentro del proyecto de otra persona.',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 160, example: 'Disenar el logo'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true),
                    new OA\Property(property: 'status', type: 'string', default: 'pending', enum: ['pending', 'in_progress', 'completed']),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2026-09-15'),
                    new OA\Property(property: 'assigned_to', description: 'Id de un usuario existente.', type: 'integer', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tarea creada; la cabecera Location apunta a su ruta aplanada.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'El proyecto es de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        // El proyecto lo fija la ruta, no el payload: no hay forma de crear
        // una tarea dentro del proyecto de otra persona.
        $task = $project->tasks()->create($request->validated());

        return TaskResource::make($task->load('assignee'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('tasks.show', $task));
    }

    /**
     * Ruta aplanada: el UUID ya es unico, pedir tambien el proyecto obligaria
     * al cliente a arrastrar un dato que el servidor no necesita.
     */
    #[OA\Get(
        path: '/tasks/{task}',
        operationId: 'tasks.show',
        summary: 'Devuelve una tarea con su proyecto y su asignado',
        description: 'Ruta aplanada: el UUID de la tarea ya es unico, asi que pedir tambien el proyecto seria ruido.',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tarea',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'La tarea pertenece al proyecto de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe, o su proyecto fue borrado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return TaskResource::make($task->load(['assignee', 'project']));
    }

    #[OA\Patch(
        path: '/tasks/{task}',
        operationId: 'tasks.update',
        summary: 'Actualiza una tarea',
        description: 'Actualizacion parcial: los campos omitidos se quedan como estaban.',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 160),
                new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true),
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed']),
                new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'assigned_to', type: 'integer', nullable: true),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tarea actualizada',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Tarea de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        // La autorizacion vive en UpdateTaskRequest::authorize(), que corre
        // antes de validar.
        $task->update($request->validated());

        return TaskResource::make($task->load('assignee'));
    }

    #[OA\Delete(
        path: '/tasks/{task}',
        operationId: 'tasks.destroy',
        summary: 'Borra una tarea',
        description: 'Borrado logico.',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Borrada'),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Tarea de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
