<?php

namespace Sofa\History;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property array $data
 * @property string $action
 * @property string $model_type
 * @property int|string $user_id
 * @property int|string $model_id
 * @property-read Carbon $created_at
 * @method static for(Model|string $model, int|string $id = null): Builder|History
 * @method static at(DateTimeInterface|string $timestamp): Builder|History
 * @method static havingData(string $key, ...$whereParams): Builder|History
 * @mixin Builder
 */
class History extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_FORCE_DELETED = 'force_deleted';

    public const ACTION_PIVOT_ATTACHED = 'pivot_attached';
    public const ACTION_PIVOT_DETACHED = 'pivot_detached';
    public const ACTION_PIVOT_UPDATED = 'pivot_updated';

    public const PIVOT_ACTIONS = [
        self::ACTION_PIVOT_ATTACHED,
        self::ACTION_PIVOT_DETACHED,
        self::ACTION_PIVOT_UPDATED,
    ];

    public static ?Hydrator $hydrator = null;

    protected $table = 'model_history';

    protected $fillable = [
        'data',
        'action',
    ];

    protected $casts = [
        'data' => 'json',
        'action' => 'string',
    ];

    public static function recreate(
        string|Model $model,
        string|int $id,
        string|DateTimeInterface $timestamp,
        array|Arrayable $relations = []
    ): ?Model {
        $hydrator = static::$hydrator ?? new Hydrator();

        return $hydrator->recreate($model, $id, $timestamp, $relations);
    }

    /**
     * @psalm-suppress MismatchingDocblockReturnType
     * @return MorphTo|Model
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * @psalm-suppress MismatchingDocblockReturnType
     * @return BelongsTo|Model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('history.user_model'), 'user_id');
    }

    public function scopeHavingData(Builder $query, string $key, ...$whereParams): Builder
    {
        $column = 'data->' . str_replace('.', '->', $key);

        $firstParam = $whereParams[0] ?? null;
        if (is_array($firstParam) || $firstParam instanceof Arrayable) {
            return $query->whereIn($column, ...$whereParams);
        }

        return $query->where($column, ...$whereParams);
    }

    public static function scopeFor(Builder $query, Model|string $model, $id = null): Builder
    {
        if (is_string($model)) {
            $model = new $model();
        }

        $id ??= $model->getKey();

        return $query
            ->orderBy('id')
            ->where('model_type', $model->getMorphClass())
            ->when($id, fn ($q) => $q->where('model_id', $id));
    }

    public static function scopeAt(Builder $query, DateTimeInterface|string $timestamp): Builder
    {
        return $query->where('created_at', '<=', Carbon::parse($timestamp));
    }
}
