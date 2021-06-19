<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $body
 * @property-read Model|Post|Category $model
 * @mixin Builder
 */
class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'body',
    ];

    /**
     * @return MorphTo|Post|Category
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
