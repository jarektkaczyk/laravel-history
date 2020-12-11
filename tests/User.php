<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sofa\History\HistoryModelMixin;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property-read Post|null $lastPost
 * @property-read Collection|Post[] $posts
 * @property-read Collection|Version[] $postVersions
 * @mixin Builder
 * @mixin HistoryModelMixin
 */
class User extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    /**
     * @return HasMany|Post
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * @return HasOne|Post
     */
    public function lastPost(): HasOne
    {
        return $this->hasOne(Post::class, 'user_id')->latest('id');
    }

    public function postVersions(): HasManyThrough
    {
        return $this->hasManyThrough(Version::class, Post::class);
    }
}
