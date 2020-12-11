<?php

namespace Sofa\History;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use function PHPUnit\Framework\isEmpty;

class HistoryListener
{
    public function handle($eventName, array $data = [])
    {
        $event = preg_replace('/eloquent\.(\w+): .*/', '$1', $eventName);
        $model = $data[0] ?? null;

        if (method_exists($this, $event) && $model && !$model instanceof History) {
            $this->$event($model);
        }
    }

    protected function created(Model $model): void
    {
        $history = new History;
        $history->model()->associate($model);
        $history->data = $this->getDirty($model);
        $history->action = __FUNCTION__;
        $history->user_id = $this->getUserId();
        $history->save();
    }

    protected function updated(Model $model): void
    {
        $dirty = $this->getDirty($model);

        if (collect($dirty)->keys()->diff($model->getUpdatedAtColumn())->isEmpty()) {
            return;
        }

        $history = new History;
        $history->model()->associate($model);
        $history->data = $dirty;
        $history->version = History::for($model)->count() + 1;
        $history->action = __FUNCTION__;
        $history->user_id = $this->getUserId();
        $history->save();
    }

    protected function deleted(Model $model): void
    {
        $history = new History;
        $history->model()->associate($model);
        $history->version = History::for($model)->count() + 1;
        $history->action = __FUNCTION__;
        $history->user_id = $this->getUserId();
        $history->save();
    }

    protected function forceDeleted(Model $model): void
    {
        $history = new History;
        $history->model()->associate($model);
        $history->version = History::for($model)->count() + 1;
        $history->action = __FUNCTION__;
        $history->user_id = $this->getUserId();
        $history->save();
    }

    protected function restored(Model $model): void
    {
        $history = new History;
        $history->model()->associate($model);
        $history->version = History::for($model)->count() + 1;
        $history->action = __FUNCTION__;
        $history->user_id = $this->getUserId();
        $history->save();
    }

    protected function getDirty(Model $model): array
    {
        return Arr::except($model->getDirty(), [
            $model->getKeyName(),
//            $model->getCreatedAtColumn(),
//            $model->getUpdatedAtColumn(),
            method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
        ]);
    }

    protected function getUserId()
    {
        $userResolver = config('sofa_history.user_resolver');

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
}
