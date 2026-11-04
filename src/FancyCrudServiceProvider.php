<?php

namespace FancyCrud;

use Illuminate\Support\ServiceProvider;

class FancyCrudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fancy-crud.php', 'fancy-crud');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fancy-crud.php' => config_path('fancy-crud.php'),
            ], 'fancy-crud-config');
        }
    }
}