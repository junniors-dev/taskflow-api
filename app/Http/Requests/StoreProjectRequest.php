<?php

namespace App\Http\Requests;

class StoreProjectRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        // Cualquier usuario autenticado puede crear proyectos propios. El
        // dueno lo asigna la relacion en el controlador, no este payload.
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
