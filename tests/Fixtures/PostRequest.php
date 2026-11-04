<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Contracts\BaseRequest;

class PostRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function commonRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'body' => ['nullable', 'string'],
        ];
    }
}
