<?php

namespace Sofa\History\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property string $uuid
 * @property string $body
 * @property-read Model|Post|Category $model
 * @mixin Builder
 */
class Comment extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'uuid';
    protected $fillable = [
        'body',
    ];

    protected static function boot()
    {
        parent::boot();
        self::creating(fn (Comment $model) => $model->setAttribute('uuid', Str::uuid()->toString()));
    }

    /**
     * @return MorphTo|Post|Category
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
