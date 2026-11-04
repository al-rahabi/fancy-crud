<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Services\BaseCrudService;

class PostService extends BaseCrudService
{
    protected string $model = Post::class;
}
