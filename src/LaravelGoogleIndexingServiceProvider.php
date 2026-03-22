<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing;

use Elfeffe\LaravelGoogleIndexing\Helpers\IndexingHelper;
use Illuminate\Support\ServiceProvider;

class LaravelGoogleIndexingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-google-indexing.php' => config_path('laravel-google-indexing.php'),
        ], 'laravel-google-indexing-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_google_indexing_records_table.php.stub' => $this->getMigrationFileName('create_google_indexing_records_table.php'),
        ], 'laravel-google-indexing-migrations');

        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-google-indexing.php',
            'laravel-google-indexing'
        );
    }

    public function register(): void
    {
        $this->app->singleton('laravel-google-indexing', function () {
            return new LaravelGoogleIndexing;
        });

        $this->app->singleton(IndexingHelper::class, function ($app) {
            return new IndexingHelper(
                $app->make(LaravelGoogleIndexing::class)
            );
        });

        $this->app->alias(IndexingHelper::class, 'google-indexing-helper');
    }

    protected function getMigrationFileName(string $migrationFileName): string
    {
        $filesystem = $this->app->make('files');
        $existingMigration = $filesystem->glob(database_path("migrations/*_{$migrationFileName}"));

        if ($existingMigration !== false && $existingMigration !== []) {
            return $existingMigration[0];
        }

        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$migrationFileName}");
    }
}
