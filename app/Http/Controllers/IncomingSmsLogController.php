<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SmsLogResource;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class IncomingSmsLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $forAdmin = $request->routeIs('admin.*');

        if ($forAdmin) {
            abort_unless($user->hasRole('Super Admin'), 403);
        } else {
            abort_unless($user->hasRole('Trader'), 403);
        }

        $linkFilter = $request->string('link_filter')->toString();

        if ($linkFilter === '') {
            $linkFilter = 'unlinked';
        }

        if (! in_array($linkFilter, ['all', 'unlinked'], true)) {
            return response()->failWithMessage('Некорректный фильтр сообщений.', 422);
        }

        $smsLogs = $this->baseQuery($user, $forAdmin)
            ->when(
                $linkFilter === 'unlinked',
                fn (Builder $query) => $query->awaitingLink()
            )
            ->when(
                $forAdmin && $request->filled('search'),
                fn (Builder $query) => $query->where('message', 'like', '%'.strtolower($request->string('search')->toString()).'%')
            )
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 10);

        $collection = SmsLogResource::collection($smsLogs);

        return response()->json([
            'success' => true,
            'unlinked_count' => self::unlinkedCountForUser($user, $forAdmin),
            ...$collection->response()->getData(true),
        ]);
    }

    public static function unlinkedCountForUser(User $user, bool $forAdmin = false): int
    {
        return (new self)->baseQuery($user, $forAdmin)
            ->awaitingLink()
            ->count();
    }

    private function baseQuery(User $user, bool $forAdmin): Builder
    {
        return SmsLog::query()
            ->incomingPayments()
            ->with([
                'device',
                'order.paymentDetail',
                'order.paymentGateway',
                ...($forAdmin ? ['user'] : []),
            ])
            ->when(
                ! $forAdmin,
                fn (Builder $query) => $query->where('user_id', $user->id)
            );
    }
}
