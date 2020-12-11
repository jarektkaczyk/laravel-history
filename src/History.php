<?php

namespace Sofa\History;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property array $data
 * @property string $action
 * @property int $version
 * @property int|string $user_id
 * @property-read Carbon $created_at
 * @method static History|Builder for(Model $model)
 * @mixin Builder
 */
class History extends Model
{
    protected $table = 'sofa_model_history';

    protected $attributes = [
        'data' => '[]',
    ];

    protected $fillable = [
        'data',
        'action',
    ];

    protected $casts = [
        'data' => 'json',
        'version' => 'int',
        'action' => 'string',
    ];

    public static function scopeFor(Builder $query, Model $model): Builder
    {
        return $query
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey());
    }

    /**
     * @param string|Model $class
     * @param string|int $id
     * @param string|int|DateTimeInterface $versionOrTime
     * @return Model|null
     */
    public static function recreate($class, $id, $versionOrTime): ?Model
    {
        /** @var string|Model $model */
        $model = new $class;
        $model->{$model->getKeyName()} = $id;

        if ($versionOrTime instanceof DateTimeInterface) {
            $versionOrTime = Carbon::instance($versionOrTime);
        }

        $history = is_int($versionOrTime)
            ? self::for($model)->where('version', '<=', $versionOrTime)->get()
            : self::for($model)->where('created_at', '<=', Carbon::parse($versionOrTime))->get();

        if ($history->isEmpty()) {
            return null;
        }

        return $history
            ->reduce(function (?Model $model, History $history) {
                if (is_null($model) || $history->action === 'forceDeleted') {
                    return null;
                }

                $attributes = $history->data + $model->getAttributes();

                // SoftDeletes
                if ($history->action === 'deleted') {
                    if (method_exists($model, 'getDeletedAtColumn')) {
                        $attributes[$model->getDeletedAtColumn()] = $model->fromDateTime($history->created_at);
                    } else {
                        return null;
                    }
                }

                if ($history->action === 'restored' && method_exists($model, 'getDeletedAtColumn')) {
                    $attributes[$model->getDeletedAtColumn()] = null;
                }

                return $model->setRawAttributes($attributes);
            }, $model);
    }

    /**
     * @return MorphTo|Model
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * @return BelongsTo|Model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('sofa_history.user_model'), 'user_id');
    }
}
