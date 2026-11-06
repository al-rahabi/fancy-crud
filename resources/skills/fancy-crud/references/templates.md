# Templates

Illustrative, typed, following the rules in `SKILL.md`. Adapt names; keep the shape.

## FormRequest (writes and index parameters)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use FancyCrud\Contracts\BaseRequest;

final class PostRequest extends BaseRequest
{
    /** @return array<string, mixed> */
    public function commonRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ];
    }
}

final class PostIndexRequest extends \Illuminate\Foundation\Http\FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'search' => ['nullable', 'string', 'max:100'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string'],
            'status' => ['nullable', 'in:draft,published'],
        ];
    }
}
```

## Service (no request, no user, no authorization)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use FancyCrud\Services\BaseCrudService;
use Illuminate\Support\Str;

final class PostService extends BaseCrudService
{
    protected string $model = Post::class;

    /**
     * @param  array{title: string, body?: string|null}  $data
     * @return array<string, mixed>
     */
    public function storeAdditionalData(array $data): array
    {
        return ['slug' => Str::slug($data['title'])]; // pure derivation from the data itself
    }
}
```

## Controller (authorize → validated → service → after logic → respond)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\PostPublished;
use App\Http\Requests\PostRequest;
use FancyCrud\Contracts\CrudController;
use Illuminate\Database\Eloquent\Model;

final class PostController extends CrudController
{
    protected ?string $resource = PostResource::class;
    protected ?string $storeRequest = PostRequest::class;
    protected ?string $updateRequest = PostRequest::class;

    // store/update/destroy are inherited: authorize → validated data → before hook → service → after hook.
    // Only override what differs:

    protected function storeData(): array
    {
        return [...parent::storeData(), 'created_by' => $this->user()?->getAuthIdentifier()]; // the actor is passed IN as data
    }

    protected function afterStore(Model $item, array $data): void
    {
        PostPublished::dispatch($item);              // after-logic lives in the controller
    }

    // service(), repository(), getPlatform() omitted
}
```

Index parameters need no wiring: `BaseIndexRequest` validates them and the controller calls `$repository->withParameters($validated)`.
Add rules by subclassing the request and setting `protected string $indexRequest = PostIndexRequest::class;` on the controller, and give
each filter its rules with `Filter::make('status')->rules('in:draft,published')`.

## Test file `tests/Feature/PostTest.php`

```php
<?php

declare(strict_types=1);

use App\Events\PostPublished;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// 1. generated CRUD suite
crudTest('posts')
    ->model(Post::class)
    ->all()
    ->record(fn () => Post::factory()->create())
    ->createPayload(['title' => 'Hello', 'body' => 'World'])
    ->invalidPayload(['title' => ''])
    ->jsonStructure(['id', 'title', 'body'])
    ->run();

// 2. everything beyond the CRUD basics
describe('after creating', function () {
    it('dispatches PostPublished', function () {
        Event::fake([PostPublished::class]);

        $this->postJson('/api/posts', ['title' => 'Hello'])->assertCreated();

        Event::assertDispatched(PostPublished::class);
    });

    it('dispatches nothing when validation fails', function () {
        Event::fake([PostPublished::class]);

        $this->postJson('/api/posts', ['title' => ''])->assertUnprocessable();

        Event::assertNotDispatched(PostPublished::class);
    });
});

describe('index', function () {
    it('does not run more queries when there are more rows', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/posts')->assertOk();

            return count(DB::getQueryLog());
        };

        Post::factory()->create();
        $one = $count();
        Post::factory()->count(5)->create();

        expect($count())->toBe($one);
    });
});

describe('bulk', function () {
    it('publishes the selected posts', function () {
        $posts = Post::factory()->count(3)->create(['status' => 'draft']);

        $this->postJson('/api/posts/bulk', [
            'action' => 'publish', 'ids' => $posts->pluck('id')->all(), 'extra' => ['status' => 'published'],
        ])->assertOk()->assertJsonPath('data.processed', 3);

        expect(Post::where('status', 'published')->count())->toBe(3);
    });
});
```

Service unit test (no HTTP, no user):

```php
it('stores a post with a slug without any request or user', function () {
    $post = app(PostService::class)->store(['title' => 'Hello World']);

    expect($post->slug)->toBe('hello-world');
});
```

Base test case: call `Model::preventLazyLoading();` in `setUp()` so a missing `with()` fails the test.
