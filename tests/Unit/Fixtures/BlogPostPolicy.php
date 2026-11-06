<?php

namespace FancyCrud\Tests\Unit\Fixtures;

use FancyCrud\Contracts\BasePolicy;

class BlogPostPolicy extends BasePolicy
{
    public function resourceName(): string
    {
        return $this->resource;
    }
}
