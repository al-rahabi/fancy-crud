<?php

use FancyCrud\Tests\Fixtures\Post;

crudTest('posts')
    ->model(Post::class)
    ->all()
    ->record(fn () => Post::create(['title' => 'Seed', 'body' => 'Seeded body']))
    ->createPayload(['title' => 'Hello', 'body' => 'World'])
    ->invalidPayload(['title' => ''])
    ->jsonStructure(['id', 'title', 'body'])
    ->assertCreated(['title' => 'Hello'])
    ->importRows([
        ['Title', 'Body'],
        ['First', 'one'],
        ['Second', 'two'],
    ])
    ->run();
