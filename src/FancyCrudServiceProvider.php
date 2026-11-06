<?php

namespace FancyCrud;

use FancyCrud\Console\SyncPermissionsCommand;
use Illuminate\Support\ServiceProvider;

class FancyCrudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fancy-crud.php', 'fancy-crud');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'fancy-crud');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);

            $this->publishes([
                __DIR__.'/../config/fancy-crud.php' => config_path('fancy-crud.php'),
            ], 'fancy-crud-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/fancy-crud'),
            ], 'fancy-crud-lang');
        }
    }
}