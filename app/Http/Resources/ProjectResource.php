<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Project
 */
#[OA\Schema(
    schema: 'Project',
    title: 'Proyecto',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '01a045d6-b05e-70c6-a3c9-29454f3eb0ad'),
        new OA\Property(property: 'name', type: 'string', example: 'Rediseno del sitio'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(
            property: 'tasks_count',
            description: 'Solo aparece cuando el endpoint lo calcula; su ausencia no significa cero.',
            type: 'integer',
            example: 6,
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            // whenCounted omite la clave si el withCount no se pidio, en vez
            // de devolver un 0 que el cliente leeria como "no tiene tareas".
            'tasks_count' => $this->whenCounted('tasks'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
