<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        // Crear una tarea modifica el proyecto, asi que el permiso que hace
        // falta es el de editarlo. Va aqui y no en el controlador para que
        // un proyecto ajeno devuelva 403 antes de mirar el payload.
        return $this->authenticatedUser()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
