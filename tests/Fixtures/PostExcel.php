<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Excel\BaseExcel;
use FancyCrud\Excel\ExcelHeader;

class PostExcel extends BaseExcel
{
    protected string $model = Post::class;

    public function headers(): array
    {
        return [
            ExcelHeader::column('title', 'Title')->required(),
            ExcelHeader::column('body', 'Body'),
        ];
    }
}
