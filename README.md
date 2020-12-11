# Eloquent History

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sofa/laravel-history.svg?style=flat-square)](https://packagist.org/packages/sofa/laravel-history)
[![GitHub Tests Action Status](https://github.com/jarektkaczyk/laravel-history/workflows/Tests/badge.svg)](https://github.com/jarektkaczyk/laravel-history/actions/workflows/run-tests.yml?branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/sofa/laravel-history.svg?style=flat-square)](https://packagist.org/packages/sofa/laravel-history)

Automatically record history of your Eloquent models

## Roadmap (contributions most welcome 🙏🏼)
- [ ] retention [configuration & command](./config/history.php)
- [ ] bundle frontend implementation to present history in a beautiful form **blade**
- [ ] bundle frontend implementation to present history in a beautiful form **vue**
- [ ] bundle frontend implementation to present history in a beautiful form **TALL stack**
- [ ] bundle frontend implementation to present history in a beautiful form **react**

## Installation

You can install the package via composer:

```bash
composer require sofa/laravel-history
```

Then publish migrations and config, then run migrations to create the necessary table:

```bash
php artisan vendor:publish --provider="Sofa\History\HistoryServiceProvider"
php artisan migrate
```

This is the contents of the published config file:

```php
return [
    /**
     * Model of the User performing actions and recorded in the history.
     *
     * @see \Sofa\History\History::user()
     */
    'user_model' => 'App\Models\User',

    /**
     * Custom user resolver for the actions recorded by the package.
     * Should be callable returning a User performing an action, or their raw identifier.
     * By default auth()->id() is used.
     *
     * @see \Sofa\History\HistoryListener::getUserId()
     */
    'user_resolver' => null,

    /**
     * **RETENTION** requires adding cleanup command to your schedule
     *
     * Retention period for the history records.
     * Accepts any parsable date string, eg.
     * '2021-01-01' -> retain anything since 2021-01-01
     * '3 months' -> retain anything no older than 3 months
     * '1 year' -> retain anything no older than 1 year
     * @see strtotime()
     *
     * @see \Sofa\History\RetentionCommand
     */
    'retention' => null,
];
```

## Usage

#### Time travel with your models:

``` php
$postFromThePast = History::recreate(Post::class, $id, '2020-12-10, ['categories']);
// or: $postFromThePast = Post::recreate($id, '2020-12-10, ['categories']);

// model attributes as of 2020-12-10:
$postFromThePast->title;

// relations as of 2020-12-10:
$postFromThePast->categories->count()

// related models attributes also as of 2020-12-10:
$postFromThePast->categories->first->name;
```


#### Get a full history/audit log of your models

``` php
$history = History::for($post)->get();

# For each update in the history you will get an entry like below:
>>> $history->first()
=> Sofa\History\History {#4320
     id: 16,
     model_type: "App\Models\Post",
     model_id: 5,
     action: "created",
     data: "{"title": "officiis", "user_id": 5, "created_at": "2021-06-07 00:00:00", "updated_at": "2021-06-07 00:00:00"}",
     user_id: null,
     created_at: "2021-06-07 00:00:00",
     updated_at: "2021-06-07 00:00:00",
   }

# And here you can see a sample of the recorded activity:
>>> $history->pluck('action')
=> Illuminate\Support\Collection {#4315
     all: [
       "created",
       "pivot_attached",
       "pivot_attached",
       "pivot_attached",
       "updated",
       "updated",
       "pivot_detached",
       "pivot_detached",
       "pivot_detached",
       "deleted",
       "restored",
       "pivot_attached",
       "pivot_attached",
     ],
   }
```

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Credits

- [Jarek Tkaczyk](https://github.com/jarektkaczyk)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
