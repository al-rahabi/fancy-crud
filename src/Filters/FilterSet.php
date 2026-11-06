<?php

namespace FancyCrud\Filters;

/**
 * An ad-hoc set of filters (used to merge a table's column filters with the
 * repository's own). It carries no defaults of its own: merge a set that already
 * has them with {@see BaseFilters::with()}.
 */
class FilterSet extends BaseFilters
{
    /**
     * @param  array<int, Filter>  $items
     */
    public function __construct(protected array $items = []) {}

    public function filters(): array
    {
        return $this->items;
    }

    public function defaults(): array
    {
        return [];
    }
}
