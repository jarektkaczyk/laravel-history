# 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sofa/history.svg?style=flat-square)](https://packagist.org/packages/sofa/history)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/sofa/history/run-tests?label=tests)](https://github.com/sofa/history/actions?query=workflow%3Arun-tests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/sofa/history.svg?style=flat-square)](https://packagist.org/packages/sofa/history)


This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/package-history-laravel.jpg?t=1" width="419px" />](https://softonsofa.com/github-ad-click/package-history-laravel)

We invest a lot of resources into creating [best in class open source packages](https://softonsofa.com/open-source). You can support us by [buying one of our paid products](https://softonsofa.com/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://softonsofa.com/about-us). We publish all received postcards on [our virtual postcard wall](https://softonsofa.com/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require sofa/history
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Sofa\History\HistoryServiceProvider" --tag="migrations"
php artisan migrate
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Sofa\History\HistoryServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

``` php
$history = new Sofa\History();
echo $history->echoPhrase('Hello, Sofa!');
```

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jarek Tkaczyk](https://github.com/jarektkaczyk)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
