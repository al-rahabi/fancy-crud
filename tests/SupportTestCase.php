<?php

namespace FancyCrud\Tests;

use FancyCrud\FancyCrudServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

/** Lightweight Testbench app (sqlite, Spatie tables, a users table) for the Unit tests. */
abstract class SupportTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FancyCrudServiceProvider::class, PermissionServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('permission.testing', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        (include __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->default('user');
            $table->timestamps();
        });

        Schema::create('toggles', function ($table) {
            $table->id();
            $table->boolean('is_active')->default(false);
        });
    }
}
