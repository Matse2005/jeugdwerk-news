<?php

namespace Matsevh\JeugdwerkNews;

use Illuminate\Support\ServiceProvider;

class NewsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->make('Matsevh\JeugdwerkNews\NewsController');
        $this->app->make('Matsevh\JeugdwerkNews\NewsProviderController');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        include __DIR__ . '/routes/web.php';
        // $this->loadRoutesFrom(__DIR__ . '/routes');
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }
}
