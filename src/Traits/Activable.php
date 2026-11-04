<?php

namespace FancyCrud\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder active(bool $active = true)
 *
 * @property string activeColumn
 *
 * @mixin Model
 */
trait Activable
{
    protected string $activeColumn = 'is_active';

    public function activeColumnName(): string
    {
        return $this->activeColumn;
    }

    public function scopeActive(Builder $builder, bool $active = true): Builder
    {
        return $builder->where($this->activeColumnName(), $active);
    }

    public function toggleActive(): bool
    {
        return $this->update([$this->activeColumnName() => ! $this->activeColumnName()]);
    }
}
