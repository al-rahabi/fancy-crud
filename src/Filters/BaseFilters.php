<?php

namespace FancyCrud\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * A resource's set of index filters. Extend it, list `Filter` objects in
 * `filters()` and point the repository at it with `$filters`.
 *
 * ```php
 * class PostFilters extends BaseFilters
 * {
 *     public function filters(): array
 *     {
 *         return [
 *             Filter::make('status'),
 *             Filter::make('title')->condition('LIKE'),
 *         ];
 *     }
 * }
 * ```
 */
abstract class BaseFilters
{
    /**
     * The resource's own filters.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Filters always available on every resource.
     *
     * @return array<int, Filter>
     */
    public function defaults(): array
    {
        return [
            Filter::make('forced_items')->using(fn (Builder $q, mixed $val) => $q->whereIn('id', Arr::wrap($val))),
        ];
    }

    /**
     * Defaults plus own filters; an own filter replaces a default of the same name.
     *
     * @return array<string, Filter>
     */
    public function all(): array
    {
        $all = [];
        foreach (array_merge($this->defaults(), $this->filters()) as $filter) {
            $all[$filter->name()] = $filter;
        }

        return $all;
    }

    /**
     * Request keys these filters read.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * A new set holding these filters plus `$filters` (same name → the new one wins).
     */
    public function with(Filter ...$filters): FilterSet
    {
        return new FilterSet([...array_values($this->all()), ...$filters]);
    }

    public function apply(Builder $query, array $requested): Builder
    {
        foreach ($this->all() as $filter) {
            $filter->apply($query, $requested);
        }

        return $query;
    }
}
