<?php

namespace FancyCrud\Tests;

use FancyCrud\FancyCrudServiceProvider;
use FancyCrud\Tests\Fixtures\PostController;
use FancyCrud\Tests\Fixtures\PostPolicy;
use FancyCrud\Tests\Fixtures\Post;
use FancyCrud\Tests\Fixtures\User;
use FancyCrud\Testing\InteractsWithCrud;
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
            Route::apiResource('posts', PostController::class)->parameters(['posts' => 'id']);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Gate::policy(Post::class, PostPolicy::class);

        $this->setUpCrud();
    }

    protected function crudUser(?array $permissions = null): Authenticatable
    {
        return new User(['id' => 1], $permissions);
    }
}
