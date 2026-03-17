<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing create()
 * @method static \Google\Service\Indexing\UrlNotificationMetadata status(string $url)
 * @method static bool update(string $url)
 * @method static bool delete(string $url)
 * @method static bool updateModel(\Illuminate\Database\Eloquent\Model $model)
 * @method static bool deleteModel(\Illuminate\Database\Eloquent\Model $model)
 * @method static bool modelNeedsIndexing(\Illuminate\Database\Eloquent\Model $model, int $daysThreshold = 30)
 */
class LaravelGoogleIndexing extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-google-indexing';
    }
} 
