<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'description', 'price_usd_impl', 'price_usd_month', 'active', 'sort'])]
class Widget extends Model
{
    protected function casts(): array
    {
        return [
            'price_usd_impl' => 'decimal:2',
            'price_usd_month' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
