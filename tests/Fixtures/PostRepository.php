<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Repositories\BaseRepository;

class PostRepository extends BaseRepository
{
    protected string $model = Post::class;
}
