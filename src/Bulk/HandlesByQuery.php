<?php

namespace FancyCrud\Bulk;

use Illuminate\Database\Eloquent\Builder;

/**
 * Set-based handling, run synchronously: one statement over the whole selection
 * (`$query->update([...])`, `$query->delete()`). No model events fire.
 */
interface HandlesByQuery
{
    /**
     * @return int|null the number of affected rows
     */
    public function byQuery(Builder $query, array $extra): ?int;
}
