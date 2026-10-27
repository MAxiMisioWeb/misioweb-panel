<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'template_key', 'value', 'done', 'source'])]
class ProjectRequirement extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PLUGIN = 'plugin';

    const CREATED_AT = null;

    protected function casts(): array
    {
        return ['done' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RequirementTemplate::class, 'template_key', 'key');
    }
}
