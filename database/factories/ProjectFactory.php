<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            // La factory puede asignar user_id aunque no sea rellenable:
            // makeInstance() construye el modelo dentro de Model::unguarded().
            'user_id' => User::factory(),
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Proyecto ya borrado logicamente, para probar que deja de responder.
     */
    public function trashed(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
