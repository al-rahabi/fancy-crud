<?php

use FancyCrud\Bulk\BulkAction;
use FancyCrud\Bulk\BulkActionJob;
use FancyCrud\Bulk\BulkRunner;
use FancyCrud\Bulk\HandlesByJob;
use FancyCrud\Bulk\HandlesBySync;
use FancyCrud\Tests\Fixtures\ArchiveBulkAction;
use FancyCrud\Tests\Fixtures\DeleteBulkAction;
use FancyCrud\Tests\Fixtures\Post;
use FancyCrud\Tests\Fixtures\PostTableRepository;
use FancyCrud\Tests\Fixtures\PublishBulkAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

function bulk(array $payload): TestResponse
{
    return test()->postJson('/api/table-posts/bulk', $payload);
}

function statuses(): array
{
    return Post::orderBy('id')->pluck('status')->all();
}

beforeEach(function () {
    $this->posts = collect([
        Post::create(['title' => 'A', 'status' => 'draft', 'is_active' => true]),
        Post::create(['title' => 'B', 'status' => 'draft', 'is_active' => false]),
        Post::create(['title' => 'C', 'status' => 'draft', 'is_active' => true]),
        Post::create(['title' => 'D', 'status' => 'published', 'is_active' => true]),
    ]);
    $this->ids = $this->posts->pluck('id');
});

describe('action definition', function () {
    it('derives name and label from the class', function () {
        expect((new PublishBulkAction)->name())->toBe('publish')
            ->and((new PublishBulkAction)->label())->toBe('Publish')
            ->and((new DeleteBulkAction)->ability())->toBe('delete')
            ->and((new PublishBulkAction)->ability())->toBe('update');
    });

    it('derives the mode from the implemented interface', function () {
        expect((new PublishBulkAction)->mode())->toBe('query')
            ->and((new DeleteBulkAction)->mode())->toBe('sync')
            ->and((new ArchiveBulkAction)->mode())->toBe('job');
    });

    it('fails loudly when no handler is implemented', function () {
        $action = new class extends BulkAction {};

        $action->mode();
    })->throws(LogicException::class);

    it('prefers query over job over sync when several are implemented', function () {
        $action = new class extends BulkAction implements HandlesByJob, HandlesBySync
        {
            public function byJob(Model $model, array $extra): void {}

            public function bySync(Model $model, array $extra): void {}
        };

        expect($action->mode())->toBe('job');
    });

    it('describes itself, including which extra keys are required', function () {
        expect((new PublishBulkAction)->describe())->toBe([
            'name' => 'publish',
            'label' => 'Publish',
            'mode' => 'query',
            'extra' => [
                'status' => ['required' => true, 'rules' => ['required', 'in:published,archived']],
                'note' => ['required' => false, 'rules' => ['nullable', 'string']],
            ],
        ]);
    });

    it('lists the actions in the index meta', function () {
        $actions = $this->getJson('/api/table-posts')->assertOk()->json('meta.table.bulk_actions');

        expect(array_column($actions, 'name'))->toBe(['publish', 'delete', 'archive'])
            ->and(array_column($actions, 'mode'))->toBe(['query', 'sync', 'job']);
    });
});

describe('selection', function () {
    it('applies to the given ids', function () {
        bulk(['action' => 'publish', 'ids' => [$this->ids[0], $this->ids[1]], 'extra' => ['status' => 'published']])
            ->assertOk()
            ->assertJsonPath('data', ['mode' => 'query', 'queued' => false, 'processed' => 2]);

        expect(statuses())->toBe(['published', 'published', 'draft', 'published']);
    });

    it('applies to a filter selection', function () {
        bulk(['action' => 'publish', 'filters' => ['is_active' => 1, 'status' => 'draft'], 'extra' => ['status' => 'archived']])
            ->assertOk()
            ->assertJsonPath('data.processed', 2);

        expect(statuses())->toBe(['archived', 'draft', 'archived', 'published']);
    });

    it('accepts the table level and built-in filters as selection', function () {
        bulk(['action' => 'publish', 'filters' => ['forced_items' => [$this->ids[3]]], 'extra' => ['status' => 'archived']])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        expect(statuses())->toBe(['draft', 'draft', 'draft', 'archived']);
    });

    it('applies to everything with all', function () {
        bulk(['action' => 'publish', 'all' => true, 'extra' => ['status' => 'archived']])
            ->assertOk()
            ->assertJsonPath('data.processed', 4);

        expect(statuses())->toBe(array_fill(0, 4, 'archived'));
    });

    it('rejects a request without a selection', function () {
        bulk(['action' => 'publish', 'extra' => ['status' => 'archived']])->assertStatus(422)->assertJsonValidationErrors('selection');

        expect(statuses())->toBe(['draft', 'draft', 'draft', 'published']);
    });

    it('rejects more than one selection kind', function () {
        bulk(['action' => 'publish', 'ids' => [1], 'all' => true, 'extra' => ['status' => 'archived']])
            ->assertStatus(422)->assertJsonValidationErrors('selection');
    });

    it('does not treat all=false as a selection', function () {
        bulk(['action' => 'publish', 'all' => false, 'extra' => ['status' => 'archived']])->assertStatus(422);
    });

    it('rejects empty ids', function () {
        bulk(['action' => 'publish', 'ids' => [], 'extra' => ['status' => 'archived']])->assertStatus(422);

        expect(statuses())->toBe(['draft', 'draft', 'draft', 'published']);
    });

    it('rejects filters that are empty or not declared instead of selecting everything', function () {
        bulk(['action' => 'publish', 'filters' => ['nope' => 'x'], 'extra' => ['status' => 'archived']])
            ->assertStatus(422)->assertJsonValidationErrors('filters');
        bulk(['action' => 'publish', 'filters' => [], 'extra' => ['status' => 'archived']])->assertStatus(422);

        expect(statuses())->toBe(['draft', 'draft', 'draft', 'published']);
    });

    it('ignores ids that do not exist', function () {
        bulk(['action' => 'publish', 'ids' => [999999], 'extra' => ['status' => 'archived']])
            ->assertOk()
            ->assertJsonPath('data.processed', 0);
    });
});

describe('action and extra data', function () {
    it('rejects an unknown action', function () {
        bulk(['action' => 'explode', 'all' => true])->assertStatus(422)->assertJsonValidationErrors('action');
    });

    it('requires the action', function () {
        bulk(['all' => true])->assertStatus(422)->assertJsonValidationErrors('action');
    });

    it('rejects missing required extra data', function () {
        bulk(['action' => 'publish', 'all' => true])->assertStatus(422)->assertJsonValidationErrors('extra.status');

        expect(statuses())->toBe(['draft', 'draft', 'draft', 'published']);
    });

    it('rejects invalid extra data', function () {
        bulk(['action' => 'publish', 'all' => true, 'extra' => ['status' => 'bogus']])->assertStatus(422)->assertJsonValidationErrors('extra.status');
    });

    it('accepts optional extra data and hands the action only validated keys', function () {
        bulk(['action' => 'publish', 'all' => true, 'extra' => ['status' => 'archived', 'note' => 'hi', 'rogue' => 'x']])->assertOk();

        expect(PublishBulkAction::$lastExtra)->toBe(['status' => 'archived', 'note' => 'hi']);
    });

    it('resolves validated data into action, selection and extra', function () {
        $input = (new BulkRunner(new PostTableRepository))->resolve(['action' => 'publish', 'filters' => ['status' => 'draft', 'x' => null], 'extra' => ['status' => 'archived']]);

        expect($input['action'])->toBeInstanceOf(PublishBulkAction::class)
            ->and($input['selection'])->toBe(['filters' => ['status' => 'draft']])
            ->and($input['extra'])->toBe(['status' => 'archived']);
    });

    it('validates filter values with the rules of the filter', function () {
        bulk(['action' => 'delete', 'filters' => ['forced_items' => 'not-an-array']])->assertStatus(422)->assertJsonValidationErrors('filters.forced_items');
    });

    it('needs no extra data for an action that declares none', function () {
        bulk(['action' => 'delete', 'ids' => [$this->ids[0]]])->assertOk();
    });
});

describe('modes', function () {
    it('runs a sync action record by record', function () {
        bulk(['action' => 'delete', 'ids' => [$this->ids[0], $this->ids[2]]])
            ->assertOk()
            ->assertJsonPath('data', ['mode' => 'sync', 'queued' => false, 'processed' => 2]);

        expect(Post::pluck('id')->all())->toBe([$this->ids[1], $this->ids[3]]);
    });

    it('runs a sync action over a filter selection', function () {
        bulk(['action' => 'delete', 'filters' => ['status' => 'draft']])->assertOk()->assertJsonPath('data.processed', 3);

        expect(Post::count())->toBe(1);
    });

    it('queues a job action instead of running it in the request', function () {
        Queue::fake();

        bulk(['action' => 'archive', 'all' => true])
            ->assertOk()
            ->assertJsonPath('data', ['mode' => 'job', 'queued' => true, 'processed' => null]);

        Queue::assertPushed(BulkActionJob::class, fn (BulkActionJob $job) => $job->selection === ['all' => true] && $job->action === ArchiveBulkAction::class);
        expect(statuses())->toBe(['draft', 'draft', 'draft', 'published']);
    });

    it('does the work when the queued job runs, in chunks', function () {
        // the default test queue connection is "sync": the job runs right away
        bulk(['action' => 'archive', 'filters' => ['status' => 'draft']])->assertOk();

        expect(statuses())->toBe(['archived', 'archived', 'archived', 'published']);
    });

    it('can be dispatched with extra data and an explicit selection', function () {
        Queue::fake();

        bulk(['action' => 'archive', 'ids' => [$this->ids[0]], 'extra' => ['ignored' => 1]])->assertOk();

        Queue::assertPushed(BulkActionJob::class, fn (BulkActionJob $job) => $job->selection === ['ids' => [$this->ids[0]]]);
    });

    it('runs the job handler directly', function () {
        (new BulkActionJob(PostTableRepository::class, ArchiveBulkAction::class, ['ids' => $this->ids->take(3)->all()]))->handle();

        expect(statuses())->toBe(['archived', 'archived', 'archived', 'published']);
    });

    it('sends the job to the queue the action names', function () {
        Queue::fake();
        $action = new class extends ArchiveBulkAction
        {
            public function queue(): ?string
            {
                return 'bulk';
            }
        };

        (new BulkRunner(new PostTableRepository))->run($action, ['all' => true]);

        Queue::assertPushedOn('bulk', BulkActionJob::class);
    });
});

describe('authorization', function () {
    it('requires the ability of the action', function () {
        $this->crudRestrictedHeaders(['update posts']);

        bulk(['action' => 'delete', 'all' => true])->assertForbidden();
        expect(Post::count())->toBe(4);

        bulk(['action' => 'publish', 'all' => true, 'extra' => ['status' => 'archived']])->assertOk();
    });

    it('rejects unauthenticated requests', function () {
        $this->crudUnauthedHeaders();

        bulk(['action' => 'publish', 'all' => true, 'extra' => ['status' => 'archived']])->assertUnauthorized();
    });

    it('answers 404 when the resource has no bulk actions', function () {
        $this->postJson('/api/posts/bulk', ['action' => 'publish', 'all' => true])->assertNotFound();
    });
});
