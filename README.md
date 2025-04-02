# Index pages in Google

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elfeffe/laravel-google-indexing.svg?style=flat-square)](https://packagist.org/packages/elfeffe/laravel-google-indexing)
[![Total Downloads](https://img.shields.io/packagist/dt/elfeffe/laravel-google-indexing.svg?style=flat-square)](https://packagist.org/packages/elfeffe/laravel-google-indexing)

Request a page to be indexed by Google using the [Indexing API](https://developers.google.com/search/apis/indexing-api/v3/quickstart).

This package is a fork of [famdirksen/laravel-google-indexing](https://packagist.org/packages/famdirksen/laravel-google-indexing) updated for Laravel 11 and 12 compatibility.

Please, take a note at the allowed pages that can be indexed using this API at https://developers.google.com/search/apis/indexing-api/v3/quickstart.

## Installation

You can install the package via composer:

```bash
composer require elfeffe/laravel-google-indexing
```

Next you have to follow the setup instructions from Google, this can be found here [Google Indexing API documentation](https://developers.google.com/search/apis/indexing-api/v3/prereqs).

You need to make a file in your storage direct, but you can override this setting in config with the key `laravel-google-indexing.google.auth_config`.

## Usage

> NOTE: this package works only for verified sites in your Google Search Console account

Inform Google about a new or updated URL:
```php
use Elfeffe\LaravelGoogleIndexing\Facades\LaravelGoogleIndexingFacade as LaravelGoogleIndexing;

LaravelGoogleIndexing::update('https://www.my-domain.com')
```

Delete an URL from the index:
```php
use Elfeffe\LaravelGoogleIndexing\Facades\LaravelGoogleIndexingFacade as LaravelGoogleIndexing;

LaravelGoogleIndexing::delete('https://www.my-domain.com')
```

Get the status of an URL:
```php
use Elfeffe\LaravelGoogleIndexing\Facades\LaravelGoogleIndexingFacade as LaravelGoogleIndexing;

LaravelGoogleIndexing::status('https://www.my-domain.com')
```

### Without using the Facade

You can also use the class directly:

```php
use Elfeffe\LaravelGoogleIndexing\LaravelGoogleIndexing;

(new LaravelGoogleIndexing)->update('https://www.my-domain.com')
```

For dealing with multiple urls, you can pass an array with multiple updated/deleted urls:
```php
use Elfeffe\LaravelGoogleIndexing\Facades\LaravelGoogleIndexingFacade as LaravelGoogleIndexing;

LaravelGoogleIndexing::multiplePublish([
    ['URL_UPDATED' => 'https://www.site.com'], 
    ['URL_DELETED' => 'https://www.site.com/deleted-url']
])
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email info@neoteo.com instead of using the issue tracker.

## Credits

- [Federico Reggiani](https://github.com/elfeffe) (Current maintainer)
- [Robin Dirksen](https://github.com/robindirksen1) (Original developer)
- [Famdirksen](https://famdirksen.nl) (Original company)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
