<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
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
