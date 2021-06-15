# Eloquent History

[![Latest Version on Packagist](https://poser.pugx.org/sofa/laravel-history/v/stable?format=flat-square)](https://packagist.org/packages/sofa/laravel-history)
[![GitHub Tests Action Status](https://github.com/jarektkaczyk/laravel-history/workflows/Tests/badge.svg)](https://github.com/jarektkaczyk/laravel-history/actions/workflows/run-tests.yml?branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/sofa/laravel-history.svg?style=flat-square)](https://packagist.org/packages/sofa/laravel-history)

No-setup history recording for your Eloquent models. Just install, migrate, and it works!

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
   
#### You can easily create an Audit Log for your users too:

```php
// User model
public function auditLog()
{
    return $this->hasMany(History::class, 'user_id');
}

// Then
$auditLog = auth()->user()->auditLog()->paginate();
```

## Additional setup & known limitations

The package offers 2 main functionalities:
- recording full history for a model
- recreating models with all relations in the past

History recording works out of the box for **all your Eloquent models** (really 😉). Additionally it will record and recreate model relations.
There are however some limitations due to relations inner workings in Laravel:

- recreating `HasMany`, `BelongsTo`, `MorphTo`, `MorphMany` & `HasManyThrough` is **fully supported out of the box**
- recording and recreating many-to-many relations requires custom pivot model in order for Laravel to fire relevant events (`BelongsToMany`, `MoprhToMany`). If you defined a custom pivot on your relation(s) already, you don't need to do anything. Otherwise, you can use provided placeholder pivot models:
    ```php
    // original relations:
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    
    
    // change to:
    public function categories()
    {
        return $this->belongsToMany(Category::class)->using(\Sofa\History\PivotEvents::class);
    }
    
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')->using(\Sofa\History\MorphPivotEvents::class);
    }
    ```

- `HasOne` relation can be recreated only when specific requirements are met: there is a single `orderBy(...)` on the relation definition and there are no _complex_ `where(...)` clauses (it **does not affect** recording history, just recreating model with relations):

   ```php
   // this will work:
   public function lastComment()
   {
       return $this->hasOne(Comment::class)->latest('id')->whereIn('status', ['approved', 'pending']);
   }
   
   // this will not work (currently...):
   public function lastComment()
   {
       return $this->hasOne(Comment::class) // no ordering
           ->where(fn ($q) => $q->where(...)->orWhere(...)); // unsupported where clause
   }
   ```
   
   * `HasOneThrough` is currently not supported at all 

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
