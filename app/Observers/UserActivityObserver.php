<?php

namespace App\Observers;

use App\Enums\UserActivityAction;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class UserActivityObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Model $model): void
    {
        services()->userActivityLog()->recordModelEvent($model, UserActivityAction::Created);
    }

    public function updated(Model $model): void
    {
        services()->userActivityLog()->recordModelEvent($model, UserActivityAction::Updated);
    }

    public function deleted(Model $model): void
    {
        services()->userActivityLog()->recordModelEvent($model, UserActivityAction::Deleted);
    }

    public function restored(Model $model): void
    {
        services()->userActivityLog()->recordModelEvent($model, UserActivityAction::Restored);
    }

    public function forceDeleted(Model $model): void
    {
        services()->userActivityLog()->recordModelEvent($model, UserActivityAction::ForceDeleted);
    }
}
