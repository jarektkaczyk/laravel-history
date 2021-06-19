<?php

namespace Sofa\History;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Sofa\History\Exceptions\RelationNotSupported;

class Hydrator
{
    public function recreate(
        string|Model $model,
        string|int $id,
        string|DateTimeInterface $timestamp,
        array|Arrayable $relations = [],
        array $attributes = []
    ): ?Model {
        $timestamp = Carbon::parse($timestamp);

        $history = History::for($model, $id)->at($timestamp)->get();
        if ($history->isEmpty()) {
            return null;
        }

        return $this->recreateRelations($this->replay($history, $attributes), $relations, $timestamp);
    }

    protected function recreateRelations(?Model $model, $relations, Carbon $timestamp): ?Model
    {
        if (!$model) {
            return null;
        }

        foreach ($relations as $relationName) {
            $model->setRelation($relationName, $this->recreateRelation($model, $relationName, $timestamp));
        }

        return $model;
    }

    /**
     * @psalm-suppress MismatchingDocblockParamType
     * @param EloquentCollection|History[] $history
     * @param array $attributes
     * @return Model|null
     * @throws RuntimeException
     */
    protected function replay(EloquentCollection $history, array $attributes = []): ?Model
    {
        /** @var History $firstEntry */
        $firstEntry = $history->first();
        $class = Model::getActualClassNameForMorph($firstEntry->model_type);
        $emptyModel = new $class();
        $emptyModel->{$emptyModel->getKeyName()} = $firstEntry->model_id;
        $isSoftDeleting = method_exists($emptyModel, 'getDeletedAtColumn');

        /** @var Model|null $recreatedModel */
        $recreatedModel = $history->reduce(function (?Model $model, History $history) use ($isSoftDeleting) {
            // Return early in case the model has been force deleted
            if (is_null($model) || $history->action === History::ACTION_FORCE_DELETED) {
                return null;
            }

            // Merge attributes from the past with current step in history
            $attributes = $history->data + $model->getAttributes();

            // Handle `deleted_at` timestamp upon SoftDeleting a model
            if ($history->action === History::ACTION_DELETED) {
                if (!$isSoftDeleting) {
                    return null;
                }

                $attributes[$model->getDeletedAtColumn()] = $model->fromDateTime($history->created_at);
            }

            // Handle `deleted_at` timestamp upon restoring SoftDeleted model
            if ($history->action === History::ACTION_RESTORED && $isSoftDeleting) {
                $attributes[$model->getDeletedAtColumn()] = null;
            }

            return $model->setRawAttributes($attributes);
        }, $emptyModel);

        // Final check in case the model was SoftDeleted at the requested point in time
        return $this->exists($recreatedModel)
            ? $recreatedModel->setRawAttributes($attributes + $recreatedModel->getAttributes())
            : null;
    }

    protected function exists(?Model $model): bool
    {
        return $model !== null
            && (
                !in_array(SoftDeletes::class, class_uses_recursive($model)) || !$model->trashed()
            );
    }

    protected function recreateRelation(Model $parent, string $relationName, Carbon $timestamp): Model|EloquentCollection|null
    {
        /** @var Relation $relation */
        $relation = $parent->{$relationName}();

        /**
         * Here we will recreate the related models in the past. It's fairly simple task for
         * MorphTo and BelongsTo relations, since we already have the specific key of the
         * related model. All we'll have to do is to recreate that model the same way.
         *
         * However, for xMany we'll have to do a bit more and first gather all the models that are currently related,
         * next all the models that were related to the parent at any point in time before. Finally we'll do some
         * gimmicks to guarantee we return only models related to the parent at the requested point in history.
         *
         * Lastly we will handle HasOne, which works reliably only in certain circumstances. That is, we need
         * to have single ordering applied on the relation and none or only simple WHERE clause. Otherwise
         * we won't be able to recreate the relation, for it would be impossible to ensure its validity.
         */
        if ($relation instanceof MorphTo) {
            return $this->recreate($parent->{$relation->getMorphType()}, $parent->{$relation->getForeignKeyName()}, $timestamp);
        }

        if ($relation instanceof BelongsTo) {
            return $this->recreate($relation->getRelated(), $parent->{$relation->getForeignKeyName()}, $timestamp);
        }

        if ($relation instanceof HasMany || $relation instanceof MorphMany) {
            return new EloquentCollection($this->recreateHasMany($relation, $parent, $timestamp));
        }

        if ($relation instanceof BelongsToMany || $relation instanceof MorphToMany) {
            return new EloquentCollection($this->recreateBelongsToMany($relation, $parent, $timestamp));
        }

        if ($relation instanceof HasManyThrough && !$relation instanceof HasOneThrough) {
            return new EloquentCollection($this->recreateHasManyThrough($relation, $parent, $timestamp));
        }

        if ($relation instanceof HasOne) {
            try {
                return $this->recreateHasOne($relation, $parent, $timestamp, Relation::noConstraints(fn () => $parent->{$relationName}()));
            } catch (RelationNotSupported $e) {
                throw new RelationNotSupported(
                    message: 'Relation ' . $relationName . ' is not supported yet in the History: ' . $relation::class,
                    previous: $e,
                );
            }
        }

        throw new RelationNotSupported(
            'Relation ' . $relationName . ' is not supported yet in the History: ' . $relation::class
        );
    }

    protected function recreateBelongsToMany(BelongsToMany $relation, Model $parent, Carbon $timestamp): Collection
    {
        // First we'll gather all ids of the related model along with their pivot attributes stored in the history
        $relatedModels = History::havingData('_pivot_table', $relation->getTable())
            ->where(
                fn ($q) => $q->for($parent)
                    ->orWhere(
                        fn ($q) => $q->for($relation->getRelated()::class)
                            ->havingData($relation->getForeignPivotKeyName(), $parent->id)
                            ->when($relation instanceof MorphToMany, fn ($q) => $q->havingData($relation->getMorphType(), $parent->getMorphClass()))
                    )
            )
            ->at($timestamp)
            ->whereIn('action', History::PIVOT_ACTIONS)
            ->get()
            ->reduce(function (Collection $relatedIds, History $history) use ($relation) {
                $pivotAttributes = collect($history->data)
                    ->filter(fn ($_, $key) => !Str::startsWith($key, '_pivot'))
                    ->toArray();

                $id = (int) $history->data[$relation->getRelatedPivotKeyName()];
                /** @psalm-suppress InvalidScalarArgument */
                $history->action === History::ACTION_PIVOT_DETACHED
                    ? $relatedIds->forget($id)
                    : $relatedIds->put($id, $pivotAttributes);

                return $relatedIds;
            }, new Collection());

        // Next we'll recreate all models from the past and populate `pivot` data for each of them
        return $relatedModels
            ->map(fn ($pivotAttributes, $id) => $this->recreate($relation->getRelated(), $id, $timestamp, [], [
                $relation->getPivotAccessor() => $relation->newPivot($pivotAttributes),
            ]))
            ->filter(fn (?Model $model) => $model)
            ->values();
    }

    protected function recreateHasOne(HasOne|MorphOne $relation, Model $parent, Carbon $timestamp, HasOne|MorphOne $relationWithoutConstraints): ?Model
    {
        // HasOne is a tricky relation to recreate in the past, so we need to make some assumptions first
        // If they are not met, then we'll simply throw an exception rather than recreating wrong data.
        $baseQuery = $relation->getQuery()->getQuery();
        if (!$baseQuery->orders || count($baseQuery->orders) !== 1) {
            throw new RelationNotSupported(
                'Unable to reliably recreate HasOne history without single sorting applied on the relation'
            );
        }

        $wheres = collect($relationWithoutConstraints->getQuery()->getQuery()->wheres);
        if ($wheres->whereNotIn('type', ['Basic', 'In', 'NotIn'])->isNotEmpty() ||
            $wheres->where('boolean', '!=', 'and')->isNotEmpty()
        ) {
            throw new RelationNotSupported(
                'Unable to reliably recreate HasOne history with custom WHERE clauses applied on the relation'
            );
        }

        $related = $relation->getRelated();
        $currentIds = $relation->pluck($related->getKeyName());
        $pastIds = History::havingData($relation->getForeignKeyName(), $parent->getKey())
            ->for($related::class)
            ->pluck('model_id');


        $orderColumn = $baseQuery->orders[0]['column'];
        $orderDirection = $baseQuery->orders[0]['direction'];

        $allModels = $pastIds
            ->merge($currentIds)
            ->unique()
            ->map(fn ($id) => $this->recreate($related, $id, $timestamp))
            ->filter(fn (?Model $model) => $this->exists($model) && $model->{$relation->getForeignKeyName()} === $parent->getKey());

        foreach ($wheres as $where) {
            $allModels = match ($where['type']) {
                'Basic' => $allModels->where($where['column'], $where['operator'], $where['value']),
                'NotIn' => $allModels->whereNotIn($where['column'], $where['values']),
                'In' => $allModels->whereIn($where['column'], $where['values']),
            };
        }

        return $allModels
            ->sortBy($orderColumn, SORT_REGULAR, strtolower($orderDirection) === 'desc')
            ->first();
    }

    protected function recreateHasMany(HasMany|MorphMany $relation, Model $parent, Carbon $timestamp): Collection
    {
        $related = $relation->getRelated();
        $currentIds = $relation->pluck($related->getKeyName());
        $pastIds = History::havingData($relation->getForeignKeyName(), $parent->getKey())
            ->for($related::class)
            ->pluck('model_id');

        return $pastIds
            ->merge($currentIds)
            ->unique()
            ->map(fn ($id) => $this->recreate($related, $id, $timestamp))
            ->filter(fn (?Model $model) => $this->exists($model) && $model->{$relation->getForeignKeyName()} === $parent->getKey())
            ->values();
    }

    protected function recreateHasManyThrough(HasManyThrough $relation, Model $parent, Carbon $timestamp): Collection
    {
        $related = $relation->getRelated();
        $currentIds = $relation->pluck($related->getQualifiedKeyName());

        $pastThroughIds = History::havingData($relation->getFirstKeyName(), $parent->getKey())
            ->for($relation->getParent())
            ->pluck('model_id');
        $pastIds = History::havingData($relation->getForeignKeyName(), $pastThroughIds)
            ->for($related::class)
            ->pluck('model_id');

        return $pastIds
            ->merge($currentIds)
            ->unique()
            ->map(fn ($id) => $this->recreate($related, $id, $timestamp))
            ->filter(fn (?Model $model) => $this->exists($model) && $pastThroughIds->contains($model->{$relation->getForeignKeyName()}))
            ->values();
    }
}
