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

class TaskController extends Controller
{
    /**
     * Tareas de un proyecto. Ruta anidada: sin el proyecto, la peticion no
     * tendria sentido.
     */
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
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return TaskResource::make($task->load(['assignee', 'project']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        // La autorizacion vive en UpdateTaskRequest::authorize(), que corre
        // antes de validar.
        $task->update($request->validated());

        return TaskResource::make($task->load('assignee'));
    }

    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
