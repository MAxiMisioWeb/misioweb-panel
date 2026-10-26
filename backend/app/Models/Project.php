<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'name', 'plan', 'fx_rate', 'status', 'published_at'])]
class Project extends Model
{
    public const STATUSES = ['draft', 'in_progress', 'published'];

    protected function casts(): array
    {
        return [
            'fx_rate' => 'decimal:4',
            'published_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function widgets(): BelongsToMany
    {
        return $this->belongsToMany(Widget::class, 'project_widgets')->orderBy('sort');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ProjectRequirement::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Secciones que aplican: las base más las de los widgets elegidos.
     * Los widgets no elegidos no piden nada.
     *
     * @return Collection<int, RequirementGroup>
     */
    public function requirementGroups(): Collection
    {
        $keys = $this->widgets()->pluck('key');

        return RequirementGroup::query()
            ->where(fn ($q) => $q->whereNull('widget_key')->orWhereIn('widget_key', $keys))
            ->orderBy('sort')
            ->with('templates')
            ->get();
    }

    /** @return Collection<int, RequirementTemplate> */
    public function requirementTemplates(): Collection
    {
        return new Collection($this->requirementGroups()->flatMap->templates->all());
    }

    /**
     * Avance: requisitos hechos sobre el total que aplica, de 0 a 100.
     *
     * @return array{done: int, total: int, percent: int}
     */
    public function progress(): array
    {
        $keys = $this->requirementTemplates()->pluck('key');
        $done = $this->requirements()->whereIn('template_key', $keys)->where('done', true)->count();
        $total = $keys->count();

        return ['done' => $done, 'total' => $total, 'percent' => $total ? (int) round($done / $total * 100) : 0];
    }
}
