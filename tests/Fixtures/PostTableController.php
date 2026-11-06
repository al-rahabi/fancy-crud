<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Repositories\BaseRepository;

class PostTableController extends PostController
{
    protected function repository(): BaseRepository
    {
        return new PostTableRepository;
    }
}
