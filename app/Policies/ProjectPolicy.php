<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Los tres metodos delegan en la misma comprobacion, pero se declaran por
     * separado a proposito: cada verbo tiene su propio permiso, de modo que
     * aflojar uno en el futuro no aflojo los otros dos sin querer.
     *
     * Esta es la segunda capa de ADR-06. La primera es que los listados se
     * construyen desde $user->projects(), asi que un recurso ajeno no puede
     * aparecer siquiera en una coleccion.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    private function owns(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
