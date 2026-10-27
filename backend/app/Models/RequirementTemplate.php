<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group', 'key', 'subgroup', 'label', 'type', 'aspect', 'hint', 'sort'])]
#[WithoutTimestamps]
class RequirementTemplate extends Model
{
    public const TYPES = ['text', 'area', 'check', 'image'];

    /** Tamaño en píxeles al que se recorta cada formato de imagen (sin agrandar fotos chicas). */
    public const ASPECT_SIZES = [
        '16:9' => [1920, 1080],
        '7:5' => [1400, 1000],
        '4:5' => [1080, 1350],
    ];

    public function requirementGroup(): BelongsTo
    {
        return $this->belongsTo(RequirementGroup::class, 'group', 'key');
    }
}
