<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assigned_to' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(TaskStatus::cases()),
            // Vencimientos repartidos entre pasado y futuro para que los
            // filtros por fecha tengan algo real que filtrar.
            'due_date' => fake()->optional()->dateTimeBetween('-1 month', '+2 months'),
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn () => ['due_date' => $date]);
    }
}
