<?php

namespace Sofa\History;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HistoryListener
{
    public function handle($event, array $data = [])
    {
        $model = $data[0] ?? null;
        if (!$model || $model instanceof History) {
            return;
        }

        $method = preg_replace('/eloquent\.(\w+): .*/', '$1', $event);
        if ($model instanceof Pivot) {
            $method = $method . 'Pivot';
        }

        if (method_exists($this, $method)) {
            $this->$method($model);
        }
    }

    protected function created(Model $model): void
    {
        $this->newRecord($model, History::ACTION_CREATED, $this->getDirty($model));
    }

    protected function updated(Model $model): void
    {
        $dirty = $this->getDirty($model);

        if (collect($dirty)->keys()->diff($model->getUpdatedAtColumn())->isEmpty()) {
            return;
        }

        $this->newRecord($model, History::ACTION_UPDATED, $dirty);
    }

    protected function deleted(Model $model): void
    {
        $this->newRecord($model, History::ACTION_DELETED);
    }

    protected function forceDeleted(Model $model): void
    {
        $this->newRecord($model, History::ACTION_FORCE_DELETED);
    }

    protected function restored(Model $model): void
    {
        $this->newRecord($model, History::ACTION_RESTORED);
    }

    protected function getDirty(Model $model): array
    {
        return Arr::except($model->getDirty(), [
            $model->getKeyName(),
            method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
        ]);
    }

    protected function getUserId()
    {
        $userResolver = config('history.user_resolver');

        if ($userResolver && is_callable($userResolver)) {
            $user = $userResolver();
            if (is_int($user) || is_string($user)) {
                return $user;
            }

            if (is_object($user) && $user instanceof Authenticatable) {
                return $user->getAuthIdentifier();
            }

            if (is_object($user) && method_exists($user, 'getKey')) {
                return $user->getKey();
            }

            if ($user) {
                return $user->id ?? null;
            }
        }

        return auth()->id();
    }

    protected function createdPivot(Pivot $pivot): void
    {
        $this->newPivotRecord($pivot, History::ACTION_PIVOT_ATTACHED);
    }

    protected function updatedPivot(Pivot $pivot): void
    {
        $this->newPivotRecord($pivot, History::ACTION_PIVOT_UPDATED);
    }

    protected function deletedPivot(Pivot $pivot): void
    {
        $this->newPivotRecord($pivot, History::ACTION_PIVOT_DETACHED);
    }

    private function newPivotRecord(Pivot $pivot, string $action): void
    {
        $model = $pivot->pivotParent;

        $data = collect($pivot->getAttributes())
            ->map(fn ($value, $key) => is_numeric($value) && Str::endsWith($key, '_id') && !is_int($value) ? (int) $value : $value)
            ->toArray();
        $data['_pivot_model'] = $pivot::class;
        $data['_pivot_table'] = $pivot->getTable();
        $data['_pivot_model_type'] = $pivot->getMorphClass();

        $this->newRecord($model, $action, $data);
    }

    private function newRecord(Model $model, string $action, array $data = []): void
    {
        $history = new History();
        $history->data = $data;
        $history->action = $action;
        $history->model()->associate($model);
        $history->user_id = $this->getUserId();
        $history->save();
    }
}
