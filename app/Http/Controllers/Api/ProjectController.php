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
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    /**
     * Lista los proyectos del usuario autenticado.
     */
    #[OA\Get(
        path: '/projects',
        operationId: 'projects.index',
        summary: 'Lista los proyectos del usuario autenticado',
        description: 'Nunca devuelve proyectos de otras personas: la consulta se construye desde la relacion del usuario.',
        security: [['bearerAuth' => []]],
        tags: ['Proyectos'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Filtra por nombre.', schema: new OA\Schema(type: 'string', maxLength: 120)),
            new OA\Parameter(name: 'sort', in: 'query', description: 'El prefijo "-" invierte el orden.', schema: new OA\Schema(type: 'string', default: '-created_at', enum: ['name', '-name', 'created_at', '-created_at'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado paginado',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Project')),
                    new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Parametro de consulta invalido', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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

    #[OA\Post(
        path: '/projects',
        operationId: 'projects.store',
        summary: 'Crea un proyecto',
        description: 'El dueno sale del token. Enviar "user_id" en el cuerpo no tiene ningun efecto.',
        security: [['bearerAuth' => []]],
        tags: ['Proyectos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 120, example: 'Rediseno del sitio'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 2000, nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Proyecto creado; la cabecera Location apunta al recurso.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Project')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return ProjectResource::make($project)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('projects.show', $project));
    }

    #[OA\Get(
        path: '/projects/{project}',
        operationId: 'projects.show',
        summary: 'Devuelve un proyecto con su numero de tareas',
        security: [['bearerAuth' => []]],
        tags: ['Proyectos'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proyecto',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Project')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'El proyecto existe pero es de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe, o fue borrado', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return ProjectResource::make($project->loadCount('tasks'));
    }

    #[OA\Patch(
        path: '/projects/{project}',
        operationId: 'projects.update',
        summary: 'Actualiza un proyecto',
        description: 'Actualizacion parcial: los campos omitidos se quedan como estaban. PUT se acepta como alias.',
        security: [['bearerAuth' => []]],
        tags: ['Proyectos'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 120),
                new OA\Property(property: 'description', type: 'string', maxLength: 2000, nullable: true),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proyecto actualizado',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Project')]),
            ),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Proyecto de otra persona. Se comprueba antes de validar, asi que llega este 403 y no un 422.', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Datos invalidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        // Sin Gate::authorize() aqui a proposito: la comprobacion vive en
        // UpdateProjectRequest::authorize(), que Laravel ejecuta antes de
        // validar. Ver el comentario en esa clase.
        $project->update($request->validated());

        return ProjectResource::make($project);
    }

    #[OA\Delete(
        path: '/projects/{project}',
        operationId: 'projects.destroy',
        summary: 'Borra un proyecto y arrastra sus tareas',
        description: 'Borrado logico. Las tareas del proyecto quedan borradas con el y dejan de responder.',
        security: [['bearerAuth' => []]],
        tags: ['Proyectos'],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Borrado'),
            new OA\Response(response: 401, description: 'Sin token', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Proyecto de otra persona', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'No existe', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function destroy(Project $project): Response
    {
        Gate::authorize('delete', $project);

        // Borrado logico: la fila queda con deleted_at y el enlace de ruta
        // deja de resolverla, asi que el recurso pasa a responder 404.
        $project->delete();

        return response()->noContent();
    }
}
