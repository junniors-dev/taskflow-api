<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * El esquema OpenAPI vive junto al codigo que produce la respuesta: si un
 * campo cambia aqui y no alli, la diferencia salta a la vista en el diff.
 *
 * @mixin User
 */
#[OA\Schema(
    schema: 'User',
    title: 'Usuario',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Usuario Demo'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'demo@taskflow.test'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
