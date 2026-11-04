<?php

declare(strict_types=1);

use FancyCrud\Testing\CrudTestBuilder;

if (! function_exists('crudTest')) {
    /**
     * Entry point for the declarative CRUD endpoint suite. Put a single
     * `crudTest('posts')->model(Post::class)->all()->run()` at the top of a Pest
     * file. Needs the Pest runner (the builder calls Pest's global `test()`).
     */
    function crudTest(string $resource): CrudTestBuilder
    {
        return CrudTestBuilder::make($resource);
    }
}
