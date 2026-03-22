# elfeffe/laravel-google-indexing

Submit URLs to Google's Indexing API from Laravel and track successful submissions in `google_indexing_records`.

This package is useful for sites that are eligible for the Google Indexing API and want:

- direct URL update/delete requests
- helper methods for quota-aware indexing
- a reusable `GoogleIndexable` trait for models
- persistent records of successful submissions

## Requirements

- PHP 8.4+
- Laravel 12 or 13
- a Google service account configured for the Indexing API

## Important note

Google only allows the Indexing API for specific content types, such as job posting and livestream pages. Check the official docs before using it broadly:

[Google Indexing API docs](https://developers.google.com/search/apis/indexing-api/v3/quickstart)

## Installation

```bash
composer require elfeffe/laravel-google-indexing:^1.0
```

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-google-indexing-config
```

Publish the migration:

```bash
php artisan vendor:publish --tag=laravel-google-indexing-migrations
```

The package now reuses an existing published `create_google_indexing_records_table` migration if one is already present, so republishing does not create duplicate migration files.

## Configuration

By default the package expects the Google auth JSON at:

```php
storage_path('google_auth_config.json')
```

You can override it in `config/laravel-google-indexing.php`:

```php
return [
    'google' => [
        'auth_config' => storage_path('google_auth_config.json'),
        'scopes' => [
            'https://www.googleapis.com/auth/indexing',
        ],
    ],
];
```

You may also pass a JSON string or array directly when instantiating the service.

## Basic usage

### Facade

```php
use Elfeffe\LaravelGoogleIndexing\Facades\LaravelGoogleIndexingFacade as LaravelGoogleIndexing;

LaravelGoogleIndexing::update('https://example.com/page');
LaravelGoogleIndexing::delete('https://example.com/page');
LaravelGoogleIndexing::status('https://example.com/page');
```

### Direct service usage

```php
use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;

$googleIndexing = new LaravelGoogleIndexing();

$googleIndexing->update('https://example.com/page');
```

### Custom auth config

```php
use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;

$googleIndexing = LaravelGoogleIndexing::forAuthConfig(
    storage_path('my-google-service-account.json')
);
```

## Model indexing

If a model exposes a canonical URL, you can use the `GoogleIndexable` trait.

```php
use Elfeffe\LaravelGoogleIndexing\Traits\GoogleIndexable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use GoogleIndexable;

    public function getGoogleIndexingUrl(): string
    {
        return route('articles.show', $this);
    }
}
```

Then:

```php
use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;

$service = new LaravelGoogleIndexing();
$service->updateModel($article);
```

## Helper usage

The helper wraps the service with daily quota tracking based on successful submissions stored in `google_indexing_records`.

```php
use Elfeffe\LaravelGoogleIndexing\Helpers\IndexingHelper;

$helper = app(IndexingHelper::class);

$helper->indexUrl('https://example.com/page');
$helper->indexUrls([
    'https://example.com/page-1',
    'https://example.com/page-2',
]);
$helper->indexModel($article);
```

### Quota helpers

```php
$helper->isQuotaExceeded();
$helper->getRemainingQuota();
```

Using the trait:

```php
Article::getTodayIndexingCount();
Article::getRemainingDailyQuota();
Article::query()->needsGoogleIndexing(30)->get();
```

## Stored records

Successful requests are stored in `google_indexing_records` with:

- `url`
- `status`
- `sent_at`
- `response_data`
- `error_message`
- optional morph relation via `indexable_type` / `indexable_id`

This lets you avoid unnecessary resubmissions and track recent indexing activity.

## Exceptions

Quota errors throw:

```php
Elfeffe\LaravelGoogleIndexing\Exceptions\GoogleQuotaExceededException
```

You should catch it if you are bulk processing URLs.

## Credits

- [Federico Reggiani](https://github.com/elfeffe)
- [Robin Dirksen](https://github.com/robindirksen1)
- [Famdirksen](https://famdirksen.nl)

## License

MIT. See `LICENSE.md`.
