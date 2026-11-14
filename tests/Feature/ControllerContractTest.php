<?php

declare(strict_types=1);

use FancyCrud\Tests\Fixtures\HookedPostController;
use FancyCrud\Tests\Fixtures\Post;
use FancyCrud\Tests\Fixtures\PostRepository;
use FancyCrud\Tests\Fixtures\PostService;
use FancyCrud\Tests\Fixtures\PostTableRepository;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    HookedPostController::$log = [];
});

describe('controller runs the logic around a write', function () {
    it('runs before and after hooks around store, with controller-supplied data', function () {
        $id = $this->postJson('/api/hooked-posts', ['title' => 'Hello'])->assertCreated()->json('data.id');

        expect(HookedPostController::$log)->toBe(['beforeStore:Hello', "afterStore:$id"])
            ->and(Post::find($id)->status)->toBe('by-user-1');
    });

    it('runs hooks around update and destroy', function () {
        $post = Post::create(['title' => 'Old']);

        $this->putJson("/api/hooked-posts/{$post->id}", ['title' => 'New'])->assertOk();
        $this->deleteJson("/api/hooked-posts/{$post->id}")->assertOk();

        expect(HookedPostController::$log)->toBe(['beforeUpdate:Old', 'afterUpdate:New', "beforeDestroy:{$post->id}", "afterDestroy:{$post->id}"]);
    });

    it('does not run any hook when the user is not authorized', function () {
        $post = Post::create(['title' => 'Keep']);
        $this->crudRestrictedHeaders(['index posts']);

        $this->postJson('/api/hooked-posts', ['title' => 'x'])->assertForbidden();
        $this->putJson("/api/hooked-posts/{$post->id}", ['title' => 'x'])->assertForbidden();
        $this->deleteJson("/api/hooked-posts/{$post->id}")->assertForbidden();

        expect(HookedPostController::$log)->toBe([])
            ->and($post->fresh()->title)->toBe('Keep');
    });

    it('does not run the after hook when validation fails', function () {
        $this->postJson('/api/hooked-posts', ['title' => ''])->assertUnprocessable();

        expect(HookedPostController::$log)->toBe([]);
    });

    it('does not run hooks for a missing record', function () {
        $this->putJson('/api/hooked-posts/999999', ['title' => 'x'])->assertNotFound();
        $this->deleteJson('/api/hooked-posts/999999')->assertNotFound();

        expect(HookedPostController::$log)->toBe([]);
    });

    it('authorizes before validating, so a forbidden user learns nothing about the rules', function () {
        $this->crudRestrictedHeaders([]);

        $this->postJson('/api/hooked-posts', ['title' => ''])->assertForbidden();
    });
});

describe('services are request-free and user-free', function () {
    it('writes with nobody logged in and no authorization', function () {
        Auth::forgetGuards();
        $service = new PostService;

        $post = $service->store(['title' => 'From console']);
        $service->update($post, ['title' => 'Changed']);

        expect(Auth::check())->toBeFalse()
            ->and($post->fresh()->title)->toBe('Changed')
            ->and($service->destroy($post->id))->toBeTrue()
            ->and(Post::count())->toBe(0);
    });

    it('answers null / false for a missing record instead of authorizing or throwing', function () {
        $service = new PostService;

        expect($service->update(999999, ['title' => 'x']))->toBeNull()
            ->and($service->destroy(999999))->toBeFalse();
    });

    it('derives extra attributes from the data only', function () {
        $service = new class extends PostService
        {
            public function storeAdditionalData(array $data): array
            {
                return ['status' => 'slug-'.strtolower($data['title'])];
            }
        };

        expect($service->store(['title' => 'ABC'])->status)->toBe('slug-abc');
    });
});

describe('index parameters are validated before the repository sees them', function () {
    it('rejects an invalid per_page', function () {
        $this->getJson('/api/table-posts?per_page=abc')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/table-posts?per_page=0')->assertUnprocessable();
    });

    it('rejects an invalid order direction', function () {
        $this->getJson('/api/table-posts?'.http_build_query(['order_by' => [['name' => 'title', 'direction' => 'sideways']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('order_by.0.direction');
    });

    it('rejects a filter value that breaks the filter rules', function () {
        $this->getJson('/api/table-posts?forced_items=1')->assertUnprocessable()->assertJsonValidationErrors('forced_items');
    });

    it('validates a non-string search and fields', function () {
        $this->getJson('/api/table-posts?search[]=a')->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson('/api/table-posts?fields[0][x]=1')->assertUnprocessable();
    });

    it('honours per_page, unlimited and search when valid', function () {
        Post::create(['title' => 'Alpha']);
        Post::create(['title' => 'Beta']);
        Post::create(['title' => 'Gamma']);

        expect($this->getJson('/api/table-posts?per_page=2')->json('data'))->toHaveCount(2)
            ->and($this->getJson('/api/table-posts?per_page=1&unlimited=1')->json('data'))->toHaveCount(1) // unlimited is opt-in
            ->and($this->getJson('/api/table-posts?unlimited=0&per_page=2')->json('data'))->toHaveCount(2);
    });

    it('honours unlimited only when the repository allows it', function () {
        Post::create(['title' => 'Alpha']);
        Post::create(['title' => 'Beta']);

        $allowing = new class extends PostRepository
        {
            protected bool $allowUnlimited = true;
        };

        expect($allowing->withParameters(['per_page' => 1, 'unlimited' => true])->index())->toHaveCount(2)
            ->and((new PostRepository)->withParameters(['per_page' => 1, 'unlimited' => true])->forceUnlimited())->toBeFalse();
    });

    it('clamps per_page to the repository maximum', function () {
        $repository = new PostRepository;

        expect($repository->withParameters(['per_page' => 100000])->getLimit())->toBe(100)
            ->and($repository->withParameters(['per_page' => 25])->getLimit())->toBe(25)
            ->and($this->getJson('/api/table-posts?per_page=5000')->json('meta.per_page'))->toBe(100);
    });

    it('keeps the search id match inside the filters', function () {
        $hidden = Post::create(['title' => 'Hidden']);
        Post::create(['title' => 'Visible']);

        $repository = (new PostRepository)->withParameters([
            'title' => 'Visible',
            'search' => (string) $hidden->id,
        ]);

        // `title = Visible AND (… OR id = hidden)` — the id match must not escape the filter
        expect($repository->index())->toHaveCount(0);
    });

    it('never reads values that no rule declares', function () {
        Post::create(['title' => 'Alpha']);

        $repository = (new PostTableRepository)->withParameters(['title' => 'nope']);

        // `title` is a declared filter of the table, so it applies; an undeclared key does not
        expect($repository->index())->toHaveCount(0)
            ->and((new PostTableRepository)->withParameters(['body' => 'nope'])->index())->toHaveCount(1);
    });
});
