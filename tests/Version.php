<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sofa\History\HistoryModelMixin;

/**
 * @property int $id
 * @property-read Post $post
 * @mixin Builder
 * @mixin HistoryModelMixin
 */
class Version extends Model
{
    protected $fillable = [
        'version',
        'post_id',
    ];

    /**
     * @return BelongsTo|Post
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
