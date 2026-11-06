<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Filters\BaseFilters;
use FancyCrud\Filters\Filter;

class PostFilters extends BaseFilters
{
    public function filters(): array
    {
        return [
            Filter::make('title'),
            Filter::make('body')->condition('LIKE'),
            Filter::make('created_at')->method('whereDate'),
            Filter::make('long_title')->using(fn ($q, $v) => $q->whereRaw('length(title) > ?', [(int) $v])),
        ];
    }
}
