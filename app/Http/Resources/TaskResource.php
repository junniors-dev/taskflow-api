<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Task
 */
#[OA\Schema(
    schema: 'Task',
    title: 'Tarea',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string', example: 'Disenar el logo'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed'], example: 'in_progress'),
        new OA\Property(
            property: 'due_date',
            description: 'Fecha sin hora: una fecha limite no tiene hora, y darsela la expone a que una conversion de huso la corra un dia.',
            type: 'string',
            format: 'date',
            nullable: true,
            example: '2026-09-15',
        ),
        new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, example: 2),
        new OA\Property(
            property: 'assignee',
            description: 'Solo cuando el endpoint carga la relacion.',
            ref: '#/components/schemas/User',
            nullable: true,
        ),
        new OA\Property(
            property: 'project',
            description: 'Solo en el detalle de una tarea.',
            ref: '#/components/schemas/Project',
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,

            // toDateString y no ISO-8601 con huso: una fecha limite no tiene
            // hora, y darle una la expone a que una conversion la corra un dia.
            'due_date' => $this->due_date?->toDateString(),

            // El id crudo siempre, para que un cliente pueda trabajar sin
            // pedir la relacion.
            'assigned_to' => $this->assigned_to,

            // whenLoaded omite la clave si la relacion no se cargo. Es
            // deliberado: convierte un N+1 en algo visible en la respuesta
            // en vez de en consultas silenciosas.
            'assignee' => $this->whenLoaded(
                'assignee',
                fn () => $this->assignee ? UserResource::make($this->assignee) : null
            ),
            'project' => $this->whenLoaded(
                'project',
                fn () => ProjectResource::make($this->project)
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
