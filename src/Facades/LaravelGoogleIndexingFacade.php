<?php

declare(strict_types=1);

namespace Elfeffe\LaravelGoogleIndexing\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing
 */
class LaravelGoogleIndexingFacade extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'LaravelGoogleIndexing';
    }
}
