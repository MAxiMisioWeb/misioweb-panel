<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sección de requisitos. widget_key nulo = sección base, la piden todos los proyectos.
 */
#[Fillable(['key', 'widget_key', 'name', 'description', 'sort'])]
#[WithoutTimestamps]
class RequirementGroup extends Model
{
    public function templates(): HasMany
    {
        return $this->hasMany(RequirementTemplate::class, 'group', 'key')->orderBy('sort');
    }
}
