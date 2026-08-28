<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Las tareas no tienen dueno propio: lo hereda del proyecto. Delegar en
     * lugar de duplicar la regla evita que las dos definiciones se separen
     * con el tiempo.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->ownsProject($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->ownsProject($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->ownsProject($user, $task);
    }

    private function ownsProject(User $user, Task $task): bool
    {
        // loadMissing y no $task->project a secas: con preventLazyLoading
        // activo, una relacion cargada de forma perezosa lanza excepcion.
        // Aqui la carga es explicita, que es justo lo que la proteccion pide.
        return $task->loadMissing('project')->project?->user_id === $user->id;
    }
}
