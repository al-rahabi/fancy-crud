<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Contracts\CrudController;
use FancyCrud\Repositories\BaseRepository;
use FancyCrud\Services\BaseCrudService;
use FancyCrud\Services\BaseService;

class PostController extends CrudController
{
    protected ?string $resource = PostResource::class;

    protected ?string $storeRequest = PostRequest::class;

    protected ?string $updateRequest = PostRequest::class;

    protected ?string $excel = PostExcel::class;

    protected function service(): BaseService|BaseCrudService
    {
        return new PostService();
    }

    protected function repository(): BaseRepository
    {
        return new PostRepository();
    }

    public function getPlatform(): string
    {
        return '';
    }
}
