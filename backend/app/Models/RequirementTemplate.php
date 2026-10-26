<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group', 'key', 'subgroup', 'label', 'type', 'hint', 'sort'])]
#[WithoutTimestamps]
class RequirementTemplate extends Model
{
    public const TYPES = ['text', 'area', 'check'];

    public function requirementGroup(): BelongsTo
    {
        return $this->belongsTo(RequirementGroup::class, 'group', 'key');
    }
}
