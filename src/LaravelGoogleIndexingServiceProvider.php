<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing;

use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;
use Elfeffe\LaravelGoogleIndexing\Helpers\IndexingHelper;
use Illuminate\Support\ServiceProvider;

class LaravelGoogleIndexingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/laravel-google-indexing.php' => config_path('laravel-google-indexing.php'),
        ], 'laravel-google-indexing-config');
        
        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/create_google_indexing_records_table.php.stub' => $this->getMigrationFileName('create_google_indexing_records_table.php'),
        ], 'laravel-google-indexing-migrations');
        
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-google-indexing.php',
            'laravel-google-indexing'
        );
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Register the main class to use with the facade
        $this->app->singleton('laravel-google-indexing', function () {
            return new LaravelGoogleIndexing;
        });
        
        // Register the helper class
        $this->app->singleton(IndexingHelper::class, function ($app) {
            return new IndexingHelper(
                $app->make(LaravelGoogleIndexing::class)
            );
        });
        
        // Register alias for helper
        $this->app->alias(IndexingHelper::class, 'google-indexing-helper');
    }
    
    /**
     * Returns existing migration file if found, else uses the current timestamp.
     */
    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');
        
        $filesystem = $this->app->make('files');
        
        return $filesystem->glob(database_path('migrations/*.php'))
            ? database_path("migrations/{$timestamp}_{$migrationFileName}")
            : database_path("migrations/{$timestamp}_{$migrationFileName}");
    }
}
