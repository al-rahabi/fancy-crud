<?php

namespace FancyCrud\Tests\Fixtures;

use FancyCrud\Filters\Filter;
use FancyCrud\Table\BaseTable;
use FancyCrud\Table\Column;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PostTable extends BaseTable
{
    protected ?string $model = Post::class;

    public function columns(): array
    {
        return [
            Column::link('title')->orderable()->filterable('LIKE')->url(fn (Model $row) => "/posts/{$row->id}"),
            Column::text('status')->orderable()->filterable(),
            Column::switch('is_active')->filterable(),
            Column::text('author_name')->label('Author')
                ->relation('author', 'name')
                ->filterable()
                ->orderable(fn (Builder $q, string $dir) => $q->orderBy(
                    Author::select('name')->whereColumn('authors.id', 'posts.author_id'),
                    $dir
                )),
            Column::text('comments_count')->count('comments')->orderable(),
            Column::text('title_upper')->optional()->select('title')->valueUsing(fn (Model $row) => strtoupper($row->title)),
            Column::text('everything')->optional()->valueUsing(fn (Model $row) => $row->title.'/'.$row->status),
            Column::text('color')->optional()->from('meta.color')->select('meta'),
            Column::text('body')->optional(),
            Column::text('slug')->optional()->orderable('posts.title'),
            Column::image('image')->optional()->disk('public'),
            Column::actions(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::make('author')->column('author_id'),
        ];
    }
}
