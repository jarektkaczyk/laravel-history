<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sofa\History\HistoryModelMixin;
use Sofa\History\MorphPivotEvents;
use Sofa\History\PivotEvents;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property-read User|null $user
 * @property-read Collection|Category[] $categories
 * @property-read Collection|Comment[] $comments
 * @property-read Collection|Tag[] $tags
 * @mixin Builder
 * @mixin HistoryModelMixin
 */
class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'body',
        'user_id',
    ];

    /**
     * @return BelongsTo|User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany|Category
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->using(PivotEvents::class)->withPivot([
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
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->using(MorphPivotEvents::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class, 'post_id');
    }
}
