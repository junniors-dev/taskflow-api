<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorizacion vive aqui y no en el controlador porque Laravel
        // ejecuta authorize() ANTES que las reglas de validacion. Asi, editar
        // un proyecto ajeno responde 403 y no un 422 detallando que esta mal
        // en un payload que el usuario nunca tuvo derecho a enviar.
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        // "sometimes" hace que PATCH sea una actualizacion realmente parcial:
        // omitir un campo lo deja como estaba, en vez de borrarlo.
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
