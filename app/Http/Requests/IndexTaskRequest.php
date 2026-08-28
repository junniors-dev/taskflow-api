<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('project'));
    }

    /**
     * Los parametros de consulta se validan como cualquier otro dato de
     * entrada: un ?status=terminada devuelve 422 con el detalle, no una lista
     * vacia que el cliente leeria como "este proyecto no tiene tareas".
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'due_before' => ['sometimes', 'date_format:Y-m-d'],
            'due_after' => ['sometimes', 'date_format:Y-m-d'],
            'search' => ['sometimes', 'string', 'max:160'],
            'sort' => ['sometimes', Rule::in([
                'due_date', '-due_date',
                'created_at', '-created_at',
                'title', '-title',
                'status', '-status',
            ])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
