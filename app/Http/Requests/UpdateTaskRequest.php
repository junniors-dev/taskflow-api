<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->authenticatedUser()->can('update', $this->route('task'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
