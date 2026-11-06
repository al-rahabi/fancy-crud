<?php

namespace FancyCrud\Tests;

use FancyCrud\FancyCrudServiceProvider;
use FancyCrud\Testing\InteractsWithCrud;
use FancyCrud\Tests\Fixtures\Post;
use FancyCrud\Tests\Fixtures\PostController;
use FancyCrud\Tests\Fixtures\PostPolicy;
use FancyCrud\Tests\Fixtures\PostTableController;
use FancyCrud\Tests\Fixtures\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use InteractsWithCrud;

    protected function getPackageProviders($app): array
    {
        return [FancyCrudServiceProvider::class, ExcelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('auth:web')->prefix('api')->group(function () {
            // static segments first, so `{id}` does not swallow them
            Route::get('posts/export', [PostController::class, 'export']);
            Route::get('posts/import/template', [PostController::class, 'importTemplate']);
            Route::post('posts/import', [PostController::class, 'import']);
            Route::post('posts/bulk', [PostController::class, 'bulk']);
            Route::apiResource('posts', PostController::class)->parameters(['posts' => 'id']);

            // same model, but the index is built by a table and bulk actions are enabled
            Route::post('table-posts/bulk', [PostTableController::class, 'bulk']);
            Route::get('table-posts', [PostTableController::class, 'index']);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(false);
            $table->string('image')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('authors', function ($table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('comments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('body')->nullable();
        });

        Gate::policy(Post::class, PostPolicy::class);

        $this->setUpCrud();
    }

    protected function crudUser(?array $permissions = null): Authenticatable
    {
        return new User(['id' => 1], $permissions);
    }
}
