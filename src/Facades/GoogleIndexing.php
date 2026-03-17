<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array indexUrl(string $url, bool $checkExisting = true)
 * @method static array indexUrls(array $urls, bool $checkExisting = true, int $delayMs = 400)
 * @method static array indexModel(\Illuminate\Database\Eloquent\Model $model, bool $checkExisting = true)
 * @method static bool isQuotaExceeded()
 * @method static int getRemainingQuota()
 */
class GoogleIndexing extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'google-indexing-helper';
    }
} 
