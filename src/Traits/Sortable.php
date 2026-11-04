<?php

namespace FancyCrud\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder sorted(string $direction = 'desc')
 *
 * @property string sortingColumn
 *
 * @mixin Model
 */
trait Sortable
{
    protected string $sortingColumn = 'sort_index';

    public function sortingColumnName(): string
    {
        return $this->sortingColumn;
    }

    public function scopeSorted(Builder $builder, string $direction = 'asc'): Builder
    {
        return $builder->orderBy($this->sortingColumnName(), $direction);
    }
}
