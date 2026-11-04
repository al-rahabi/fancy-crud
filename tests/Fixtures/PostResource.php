<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Contracts\BaseResource;
use Illuminate\Http\Request;

class PostResource extends BaseResource
{
    protected function toDefaultArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
