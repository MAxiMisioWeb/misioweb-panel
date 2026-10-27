<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Estado interno del trabajo de la agencia en una sección del proyecto. */
#[Fillable(['project_id', 'group', 'work_status'])]
class ProjectSection extends Model
{
    public const WORK_STATUSES = ['todo' => 'Sin empezar', 'doing' => 'En curso', 'done' => 'Listo'];

    const CREATED_AT = null;

    // Clave compuesta (project_id, group): se escribe con upsert, no con save().
    public $incrementing = false;
}
