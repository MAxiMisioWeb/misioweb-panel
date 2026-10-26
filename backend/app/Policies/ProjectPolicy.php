<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /** Cargar requisitos: la agencia en cualquier proyecto; el cliente solo en el suyo, ya aprobado. */
    public function update(User $user, Project $project): bool
    {
        if ($user->isAgency()) {
            return true;
        }

        return $user->role === User::ROLE_CLIENT
            && $user->client_id === $project->client_id
            && in_array($project->status, ['in_progress', 'published'], true);
    }

    /** Precios, widgets, estados internos y publicación: solo la agencia. */
    public function manage(User $user, Project $project): bool
    {
        return $user->isAgency();
    }
}
