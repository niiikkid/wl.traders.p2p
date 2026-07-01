<?php

namespace App\Queries\Eloquent;

use App\Models\UserActivityLog;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\UserActivityLogQueries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserActivityLogQueriesEloquent implements UserActivityLogQueries
{
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator
    {
        return UserActivityLog::query()
            ->with(['actor', 'impersonator'])
            ->when($filters->startDate, function (Builder $query) use ($filters): void {
                $query->whereDate('created_at', '>=', $filters->startDate);
            })
            ->when($filters->endDate, function (Builder $query) use ($filters): void {
                $query->whereDate('created_at', '<=', $filters->endDate);
            })
            ->when($filters->user, function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    if (is_numeric($filters->user)) {
                        $query->orWhere('actor_user_id', (int) $filters->user);
                    }

                    $query->orWhereRelation('actor', 'email', 'LIKE', '%'.$filters->user.'%');
                });
            })
            ->when(! empty($filters->activityActions), function (Builder $query) use ($filters): void {
                $query->whereIn('action', $filters->activityActions);
            })
            ->when(! empty($filters->activitySubjectTypes), function (Builder $query) use ($filters): void {
                $query->whereIn('subject_type', $filters->activitySubjectTypes);
            })
            ->when($filters->subjectId, function (Builder $query) use ($filters): void {
                $query->where('subject_id', $filters->subjectId);
            })
            ->when($filters->uuid, function (Builder $query) use ($filters): void {
                $query->where('subject_uuid', 'LIKE', '%'.$filters->uuid.'%');
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }
}
