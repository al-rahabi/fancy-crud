<?php

use FancyCrud\Tests\Fixtures\Post;
use FancyCrud\Tests\Fixtures\PostRepository;

function filteredTitles(array $query): array
{
    request()->replace($query);

    return (new PostRepository)->index()->pluck('title')->sort()->values()->all();
}

beforeEach(function () {
    Post::create(['title' => 'Alpha', 'body' => 'red apple']);
    Post::create(['title' => 'Beta', 'body' => 'green apple']);
    Post::create(['title' => 'Gamma Long Title', 'body' => 'pear']);
});

it('applies no filter when nothing is requested', function () {
    expect(filteredTitles([]))->toHaveCount(3);
});

it('filters by equality, condition, method and callback', function () {
    expect(filteredTitles(['title' => 'Beta']))->toBe(['Beta'])
        ->and(filteredTitles(['body' => '%apple']))->toBe(['Alpha', 'Beta'])
        ->and(filteredTitles(['created_at' => now()->toDateString()]))->toHaveCount(3)
        ->and(filteredTitles(['long_title' => 10]))->toBe(['Gamma Long Title']);
});

it('supports the built-in forced_items filter', function () {
    $id = Post::where('title', 'Alpha')->value('id');

    expect(filteredTitles(['forced_items' => [$id]]))->toBe(['Alpha']);
});

it('ignores request keys that are not declared filters', function () {
    expect(filteredTitles(['id' => 999, 'unknown' => 'x']))->toHaveCount(3);
});
