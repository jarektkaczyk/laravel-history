<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Sofa\History\MorphPivotEvents;
use Sofa\History\PivotEvents;

/**
 * @property int $id
 * @property string $name
 * @property-read Collection|Comment[] $comments
 * @property-read Collection|Tag[] $tags
 * @property-read Collection|Post[] $posts
 * @mixin Builder
 */
class Category extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsToMany|Post
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->using(PivotEvents::class)->withPivot([
            'extra_value',
            'another_value',
        ]);
    }

    /**
     * @return MorphMany|Comment
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'model');
    }

    /**
     * @return MorphToMany|Tag
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable')->using(MorphPivotEvents::class);
    }
}
