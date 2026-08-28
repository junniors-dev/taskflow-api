<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends ApiFormRequest
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
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }
}
