<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Sofa\History\MorphPivotEvents;

/**
 * @property int $id
 * @property string $name
 * @property-read Collection|Category[] $categories
 * @property-read Collection|Comment[] $posts
 * @mixin Builder
 */
class Tag extends Model
{
    protected $fillable = [
        'name',
    ];

    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'taggable')->using(MorphPivotEvents::class);
    }

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable')->using(MorphPivotEvents::class);
    }
}
