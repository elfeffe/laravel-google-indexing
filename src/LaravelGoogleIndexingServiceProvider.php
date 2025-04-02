<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing;

use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;
use Illuminate\Support\ServiceProvider;

class LaravelGoogleIndexingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path('laravel-google-indexing.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'laravel-google-indexing');

        $this->app->singleton('laravel_google_indexing', function () {
            return new LaravelGoogleIndexing();
        });
    }

    /**
     * Get the config file path.
     */
    protected function getConfigPath(): string
    {
        return __DIR__ . '/../config/laravel-google-indexing.php';
    }
}
