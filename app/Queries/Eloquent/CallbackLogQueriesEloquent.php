<?php

namespace App\Queries\Eloquent;

use App\Models\CallbackLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\CallbackLogQueries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CallbackLogQueriesEloquent implements CallbackLogQueries
{
    /**
     * Получить пагинированный список логов колбеков для админки
     *
     * @param TableFiltersValue $filters
     * @return LengthAwarePaginator
     */
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator
    {
        return CallbackLog::query()
            ->with('callbackable')
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->whereHasMorph('callbackable', '*', function ($q) use ($filters) {
                    $q->where('uuid', 'LIKE', '%' . $filters->uuid . '%');
                });
            })
            ->when($filters->merchant, function ($query) use ($filters) {
                $query->whereHasMorph('callbackable', [Order::class, Payout::class], function ($q) use ($filters) {
                    $q->whereRelation('merchant', 'name', 'LIKE', '%' . $filters->merchant . '%')
                        ->orWhereRelation('merchant', 'uuid', 'LIKE', '%' . $filters->merchant . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }
} 