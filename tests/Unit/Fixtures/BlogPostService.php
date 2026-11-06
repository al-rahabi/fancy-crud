<?php

namespace FancyCrud\Tests\Unit\Fixtures;

use FancyCrud\Services\BaseCrudService;

class BlogPostService extends BaseCrudService
{
    protected string $model = BlogPost::class;
}
