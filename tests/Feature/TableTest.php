<?php

use FancyCrud\Filters\BaseFilters;
use FancyCrud\Filters\Filter;
use FancyCrud\Table\Column;
use FancyCrud\Tests\Fixtures\Author;
use FancyCrud\Tests\Fixtures\Comment;
use FancyCrud\Tests\Fixtures\Post;
use FancyCrud\Tests\Fixtures\PostRepository;
use FancyCrud\Tests\Fixtures\PostTable;
use FancyCrud\Tests\Fixtures\PostTableRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** Index of the table-enabled resource. */
function tableIndex(array $query = []): TestResponse
{
    return test()->getJson('/api/table-posts'.($query ? '?'.http_build_query($query) : ''));
}

function tableRows(array $query = []): array
{
    return tableIndex($query)->assertOk()->json('data');
}

/** SQL of the statements run by `$callback`. */
function sqlOf(Closure $callback): array
{
    DB::enableQueryLog();
    $callback();
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    DB::flushQueryLog();

    return $queries;
}

beforeEach(function () {
    $this->ann = Author::create(['name' => 'Ann']);
    $this->bob = Author::create(['name' => 'Bob']);

    $this->p1 = Post::create(['title' => 'Alpha', 'body' => 'a', 'status' => 'draft', 'is_active' => true, 'author_id' => $this->bob->id, 'image' => 'covers/a.png', 'meta' => ['color' => 'red']]);
    $this->p2 = Post::create(['title' => 'Beta', 'body' => 'b', 'status' => 'published', 'is_active' => false, 'author_id' => $this->ann->id, 'image' => 'https://cdn.test/b.png']);
    $this->p3 = Post::create(['title' => 'Gamma', 'body' => 'c', 'status' => 'published', 'is_active' => true]);

    Comment::create(['post_id' => $this->p1->id]);
    Comment::create(['post_id' => $this->p1->id]);
    Comment::create(['post_id' => $this->p2->id]);
});

describe('fields', function () {
    it('returns the default columns when no fields are requested', function () {
        $rows = tableRows();

        expect($rows)->toHaveCount(3)
            ->and(array_keys($rows[0]))->toBe(['id', 'title', 'status', 'is_active', 'author_name', 'comments_count', 'actions']);
    });

    it('returns only the requested fields, in the requested order', function () {
        $rows = tableRows(['fields' => ['status', 'title']]);

        expect(array_keys($rows[0]))->toBe(['id', 'status', 'title']);
    });

    it('accepts a comma separated list', function () {
        expect(array_keys(tableRows(['fields' => 'body,status'])[0]))->toBe(['id', 'body', 'status']);
    });

    it('ignores unknown and duplicate fields', function () {
        expect(array_keys(tableRows(['fields' => ['nope', 'status', 'status']])[0]))->toBe(['id', 'status']);
    });

    it('falls back to the defaults when no requested field is valid', function () {
        expect(array_keys(tableRows(['fields' => ['nope']])[0]))->toContain('title', 'actions');
    });

    it('describes every column in meta so the client can choose', function () {
        $table = tableIndex(['fields' => ['title']])->assertOk()->json('meta.table');

        expect($table['selected'])->toBe(['title'])
            ->and(array_column($table['columns'], 'name'))->toContain('title', 'body', 'image', 'actions');

        $author = collect($table['columns'])->firstWhere('name', 'author_name');
        expect($author)->toMatchArray(['label' => 'Author', 'type' => 'text', 'default' => true, 'orderable' => true, 'filterable' => ['author_name']]);

        $body = collect($table['columns'])->firstWhere('name', 'body');
        expect($body)->toMatchArray(['default' => false, 'orderable' => false, 'filterable' => []]);

        expect(collect($table['columns'])->firstWhere('name', 'actions')['meta'])->toBe(['abilities' => ['show', 'update', 'delete']]);
    });

    it('keeps the pagination meta next to the table meta', function () {
        $meta = tableIndex(['per_page' => 2])->assertOk()->json('meta');

        expect($meta)->toHaveKeys(['table', 'total', 'per_page', 'current_page', 'last_page', 'pages_count'])
            ->and($meta['total'])->toBe(3);
    });
});

describe('query building', function () {
    it('selects only the requested database columns', function () {
        $sql = implode(' | ', sqlOf(fn () => tableRows(['fields' => ['title']])));

        expect($sql)->toContain('select "posts"."id", "posts"."title" from "posts"')
            ->and($sql)->not->toContain('"body"')
            ->and($sql)->not->toContain('"authors"')
            ->and($sql)->not->toContain('"comments"');
    });

    it('eager loads a relation only when its column is requested', function () {
        $without = implode(' | ', sqlOf(fn () => tableRows(['fields' => ['title']])));
        $with = implode(' | ', sqlOf(fn () => tableRows(['fields' => ['author_name']])));

        expect($without)->not->toContain('from "authors"')
            ->and($with)->toContain('from "authors"')
            ->and($with)->toContain('"posts"."author_id"');
    });

    it('does not run a count query unless the count column is requested', function () {
        $sql = implode(' | ', sqlOf(fn () => tableRows(['fields' => ['title']])));
        expect($sql)->not->toContain('comments');

        $sql = implode(' | ', sqlOf(fn () => tableRows(['fields' => ['comments_count']])));
        expect($sql)->toContain('comments');
    });

    it('selects every column when a custom getter does not declare what it reads', function () {
        $sql = implode(' | ', sqlOf(fn () => tableRows(['fields' => ['everything']])));

        expect($sql)->toContain('select * from "posts"');
    });

    it('takes the model from the table when the repository declares none', function () {
        expect((new PostTableRepository)->getModel())->toBe(Post::class);
    });

    it('selects the foreign key of a relation even when it is not requested itself', function () {
        $rows = tableRows(['fields' => ['author_name'], 'order_by' => [['name' => 'title', 'direction' => 'asc']]]);

        expect(collect($rows)->pluck('author_name')->all())->toBe(['Bob', 'Ann', null]);
    });
});

describe('values', function () {
    it('returns plain text, relation, count and fixed id', function () {
        $row = collect(tableRows(['fields' => ['title', 'author_name', 'comments_count', 'status']]))->firstWhere('id', $this->p1->id);

        expect($row)->toMatchArray([
            'id' => $this->p1->id,
            'author_name' => 'Bob',
            'comments_count' => 2,
            'status' => 'draft',
        ]);
    });

    it('returns null for a relation that is missing', function () {
        $row = collect(tableRows(['fields' => ['author_name']]))->firstWhere('id', $this->p3->id);

        expect($row['author_name'])->toBeNull();
    });

    it('returns a link with label and url', function () {
        $row = collect(tableRows(['fields' => ['title']]))->firstWhere('id', $this->p1->id);

        expect($row['title'])->toBe(['label' => 'Alpha', 'url' => "/posts/{$this->p1->id}"]);
    });

    it('casts a switch to a boolean', function () {
        $rows = collect(tableRows(['fields' => ['is_active']]));

        expect($rows->firstWhere('id', $this->p1->id)['is_active'])->toBeTrue()
            ->and($rows->firstWhere('id', $this->p2->id)['is_active'])->toBeFalse();
    });

    it('turns an image path into a disk url and leaves absolute urls alone', function () {
        $rows = collect(tableRows(['fields' => ['image']]));

        expect($rows->firstWhere('id', $this->p1->id)['image'])->toEndWith('/covers/a.png')
            ->and($rows->firstWhere('id', $this->p1->id)['image'])->not->toBe('covers/a.png')
            ->and($rows->firstWhere('id', $this->p2->id)['image'])->toBe('https://cdn.test/b.png')
            ->and($rows->firstWhere('id', $this->p3->id)['image'])->toBeNull();
    });

    it('supports a custom getter with declared columns', function () {
        $row = collect(tableRows(['fields' => ['title_upper']]))->firstWhere('id', $this->p1->id);

        expect($row['title_upper'])->toBe('ALPHA');
    });

    it('supports a custom getter reading everything', function () {
        $row = collect(tableRows(['fields' => ['everything']]))->firstWhere('id', $this->p1->id);

        expect($row['everything'])->toBe('Alpha/draft');
    });

    it('reads a nested path of a column', function () {
        $rows = collect(tableRows(['fields' => ['color']]));

        expect($rows->firstWhere('id', $this->p1->id)['color'])->toBe('red')
            ->and($rows->firstWhere('id', $this->p2->id)['color'])->toBeNull();
    });

    it('reports per-row action abilities for the current user', function () {
        $this->crudRestrictedHeaders(['index posts', 'update posts']);

        $row = tableRows(['fields' => ['actions']])[0];

        expect($row['actions'])->toBe(['show' => false, 'update' => true, 'delete' => false]);
    });

    it('reports every ability for a fully privileged user', function () {
        expect(tableRows(['fields' => ['actions']])[0]['actions'])->toBe(['show' => true, 'update' => true, 'delete' => true]);
    });
});

describe('ordering', function () {
    it('orders by an orderable column', function () {
        $rows = tableRows(['fields' => ['title'], 'order_by' => [['name' => 'title', 'direction' => 'desc']]]);

        expect(collect($rows)->pluck('title.label')->all())->toBe(['Gamma', 'Beta', 'Alpha']);
    });

    it('ignores a column that is not orderable', function () {
        $rows = tableRows(['fields' => ['body'], 'order_by' => [['name' => 'body', 'direction' => 'asc']]]);

        // falls through: unordered request keeps insertion order of the default (id desc is not applied when order_by is present)
        expect($rows)->toHaveCount(3);
    });

    it('orders with a custom query', function () {
        $rows = tableRows(['fields' => ['author_name'], 'order_by' => [['name' => 'author_name', 'direction' => 'asc']]]);

        // nulls first in sqlite ascending: Gamma (none), Beta (Ann), Alpha (Bob)
        expect(collect($rows)->pluck('author_name')->all())->toBe([null, 'Ann', 'Bob']);
    });

    it('orders by a count', function () {
        $rows = tableRows(['fields' => ['comments_count'], 'order_by' => [['name' => 'comments_count', 'direction' => 'desc']]]);

        expect(collect($rows)->pluck('comments_count')->all())->toBe([2, 1, 0]);
    });

    it('orders by another database column when a string is given', function () {
        $rows = tableRows(['fields' => ['status'], 'order_by' => [['name' => 'slug', 'direction' => 'desc']]]);

        expect($rows)->toHaveCount(3);
        expect(collect(sqlOf(fn () => tableRows(['order_by' => [['name' => 'slug', 'direction' => 'desc']]])))->implode(' '))
            ->toContain('order by "posts"."title" desc');
    });

    it('orders by the default when nothing is requested', function () {
        expect(collect(tableRows())->pluck('id')->all())->toBe([$this->p3->id, $this->p2->id, $this->p1->id]);
    });

    it('orders by a column even if it is not among the returned fields', function () {
        $rows = tableRows(['fields' => ['status'], 'order_by' => [['name' => 'title', 'direction' => 'asc']]]);

        expect(collect($rows)->pluck('id')->all())->toBe([$this->p1->id, $this->p2->id, $this->p3->id]);
    });
});

describe('filters', function () {
    it('filters through a filterable column', function () {
        $rows = tableRows(['title' => 'Al%']);

        expect(collect($rows)->pluck('id')->all())->toBe([$this->p1->id]);
    });

    it('filters on a column that is not returned', function () {
        $rows = tableRows(['fields' => ['title'], 'status' => 'published']);

        expect($rows)->toHaveCount(2);
    });

    it('filters a boolean switch', function () {
        expect(tableRows(['is_active' => 1]))->toHaveCount(2);
    });

    it('filters through the relation of a relation column', function () {
        $rows = tableRows(['author_name' => 'Ann']);

        expect(collect($rows)->pluck('id')->all())->toBe([$this->p2->id]);
    });

    it('supports table level filters with their own column', function () {
        $rows = tableRows(['author' => $this->bob->id]);

        expect(collect($rows)->pluck('id')->all())->toBe([$this->p1->id]);
    });

    it('combines filters', function () {
        expect(tableRows(['status' => 'published', 'is_active' => 1]))->toHaveCount(1);
    });

    it('keeps the built-in forced_items filter', function () {
        $rows = tableRows(['forced_items' => [$this->p2->id, $this->p3->id]]);

        expect($rows)->toHaveCount(2);
    });

    it('ignores undeclared filters', function () {
        expect(tableRows(['body' => 'a', 'nope' => 'x']))->toHaveCount(3);
    });

    it('lets the repository filters win over a column filter of the same name', function () {
        $repository = new class extends PostTableRepository
        {
            protected ?string $filters = RepoStatusFilters::class;
        };

        $ids = $repository->withParameters(['status' => 'draft'])->index()->pluck('id')->all();

        // RepoStatusFilters ignores the value and always matches 'published'
        expect($ids)->toHaveCount(2);
    });
});

class RepoStatusFilters extends BaseFilters
{
    public function filters(): array
    {
        return [Filter::make('status')->value('published')];
    }
}

describe('column definition', function () {
    it('derives the label and exposes the type', function () {
        $column = Column::switch('is_active');

        expect($column->getLabel())->toBe('Is Active')
            ->and($column->describe())->toMatchArray(['name' => 'is_active', 'type' => 'switch', 'default' => true, 'orderable' => false]);
    });

    it('defaults the default columns to the non-optional ones', function () {
        expect((new PostTable)->defaultColumns())->toBe(['title', 'status', 'is_active', 'author_name', 'comments_count', 'actions']);
    });

    it('lets a table choose the default columns explicitly', function () {
        $table = new class extends PostTable
        {
            public function defaultColumns(): array
            {
                return ['status'];
            }
        };

        expect(array_map(fn ($c) => $c->name(), $table->selected()))->toBe(['status']);
    });

    it('still works for a repository without a table', function () {
        $repository = new PostRepository;

        expect($repository->table())->toBeNull()
            ->and($repository->bulkActions())->toBe([]);
    });

    it('is authorized like any index', function () {
        $this->crudRestrictedHeaders([]);

        tableIndex()->assertForbidden();
    });
});

describe('the viewer is an explicit input', function () {
    it('checks actions for the given user, not for whoever is logged in', function () {
        // logged in as a fully privileged user (see TestCase), but the row is rendered for a restricted one
        $table = new PostTable;
        $columns = $table->selected(['actions']);
        $viewer = new FancyCrud\Tests\Fixtures\User(['id' => 2], ['update posts']);

        expect($table->row($this->p1, $columns, $viewer)['actions'])->toBe(['show' => false, 'update' => true, 'delete' => false])
            ->and($table->row($this->p1, $columns, new FancyCrud\Tests\Fixtures\User(['id' => 3], null))['actions'])
            ->toBe(['show' => true, 'update' => true, 'delete' => true]);
    });

    it('treats a missing user as a guest', function () {
        $table = new PostTable;

        expect($table->row($this->p1, $table->selected(['actions']), null)['actions'])
            ->toBe(['show' => false, 'update' => false, 'delete' => false]);
    });

    it('hands the user to custom getters and link urls', function () {
        $column = Column::text('who')->select('title')->valueUsing(fn (Model $row, $user) => $user?->getAuthIdentifier());
        $link = Column::link('title')->url(fn (Model $row, $user) => "/u/{$user?->getAuthIdentifier()}/posts/{$row->id}");
        $viewer = new FancyCrud\Tests\Fixtures\User(['id' => 7]);

        expect($column->resolve($this->p1, $viewer))->toBe(7)
            ->and($column->resolve($this->p1, null))->toBeNull()
            ->and($link->resolve($this->p1, $viewer))->toBe(['label' => 'Alpha', 'url' => "/u/7/posts/{$this->p1->id}"]);
    });

    it('keeps rows free of auth() so a table can be rendered from a console command', function () {
        Illuminate\Support\Facades\Auth::forgetGuards();
        $table = new PostTable;

        $rows = $table->rows(Post::all(), $table->selected(['title', 'actions']), null);

        expect($rows)->toHaveCount(3)
            ->and($rows[0]['actions'])->toBe(['show' => false, 'update' => false, 'delete' => false]);
    });
});
