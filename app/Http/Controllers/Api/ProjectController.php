<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * Lista los proyectos del usuario autenticado.
     */
    public function index(IndexProjectRequest $request): AnonymousResourceCollection
    {
        $sort = $request->string('sort', '-created_at')->toString();
        $descending = str_starts_with($sort, '-');

        // La consulta arranca desde la relacion del usuario, nunca desde
        // Project::query() con un filtro anadido despues. Un where olvidado
        // en un listado filtra la tabla entera de una sola vez; aqui no hay
        // forma de escribirlo mal.
        $projects = $request->user()->projects()
            ->withCount('tasks')
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%')
            )
            ->orderBy(ltrim($sort, '-'), $descending ? 'desc' : 'asc')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return ProjectResource::make($project)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('projects.show', $project));
    }

    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return ProjectResource::make($project->loadCount('tasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        // Sin Gate::authorize() aqui a proposito: la comprobacion vive en
        // UpdateProjectRequest::authorize(), que Laravel ejecuta antes de
        // validar. Ver el comentario en esa clase.
        $project->update($request->validated());

        return ProjectResource::make($project);
    }

    public function destroy(Project $project): Response
    {
        Gate::authorize('delete', $project);

        // Borrado logico: la fila queda con deleted_at y el enlace de ruta
        // deja de resolverla, asi que el recurso pasa a responder 404.
        $project->delete();

        return response()->noContent();
    }
}
