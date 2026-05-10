<?php

namespace App\Http\Controllers\Admin;

use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreRequest;
use App\Http\Requests\Admin\User\UpdateRequest;
use App\Http\Requests\Admin\User\UpdateTeamRequest;
use App\Http\Resources\UserResource;
use App\Models\Order;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $fromArchive = request()->tab === 'archived';

        $users = User::query()
            ->with(['roles', 'wallet', 'userTeam'])
            ->when($fromArchive, function ($query) {
                $query->whereNotNull('archived_at');
            }, function ($query) {
                $query->whereNull('archived_at');
            })
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('email', 'like', '%'.$filters->user.'%');
                    $query->orWhere('name', 'like', '%'.$filters->user.'%');
                });
            })
            ->when($filters->online, function ($query) {
                $query->where('is_online', true);
            })
            ->when($filters->traffic_disabled, function ($query) {
                $query->where('stop_traffic', true);
            })
            ->when(! empty($filters->roles), function ($query) use ($filters) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->whereIn('name', $filters->roles);
                });
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $users = UserResource::collection($users);

        // Получаем данные для фильтров
        $filtersVariants = $this->getFiltersData();

        return Inertia::render('User/Index', compact('users', 'filters', 'filtersVariants'));
    }

    // legacy Inertia page removed (create via modal + axios)

    public function roles()
    {
        $roles = Role::query()
            ->where('name', '!=', 'Provider Liquidity')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function teamLeaders()
    {
        $teamLeaders = User::query()
            ->role('Team Leader')
            ->select('id', 'email')
            ->orderBy('email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $teamLeaders,
        ]);
    }

    public function agents()
    {
        $agents = User::query()
            ->role('Agent')
            ->select('id', 'email')
            ->orderBy('email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    public function store(StoreRequest $request)
    {
        $dto = UserCreateDTO::makeFromRequest($request->validated());
        services()->user()->create($dto);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('admin.users.index');
    }

    // legacy Inertia page removed (edit via modal + axios)

    public function show(User $user)
    {
        $user->load('roles', 'meta', 'teamLeader', 'agent', 'userTeam');
        $user = UserResource::make($user)->resolve();

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(UpdateRequest $request, User $user)
    {
        $dto = UserUpdateDTO::makeFromRequest($request->validated());
        services()->user()->update($dto, $user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('admin.users.index');
    }

    public function updateTeam(UpdateTeamRequest $request, User $user)
    {
        $user->update([
            'user_team_id' => $request->validated('user_team_id'),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function toggleOnline(Request $request, User $user)
    {
        if ($user->archived_at !== null && (int) $request->is_online) {
            return;
        }

        if ((int) $user->is_online !== (int) $request->is_online) {
            if ($user->stop_traffic && (int) $request->is_online) {
                return;
            }

            $user->update(['is_online' => ! $user->is_online]);
        }
    }

    public function archive(User $user)
    {
        if (! $user->hasRole('Trader')) {
            return redirect()->back()->with('error', 'Архивировать можно только трейдера.');
        }

        if ($user->archived_at !== null) {
            return redirect()->back()->with('error', 'Пользователь уже в архиве.');
        }

        $hasActiveOrder = Order::query()
            ->where('trader_id', $user->id)
            ->where('status', OrderStatus::PENDING)
            ->exists();

        if ($hasActiveOrder) {
            return redirect()->back()->with('error', 'Нельзя архивировать трейдера с активной сделкой.');
        }

        $hasActivePayout = Payout::query()
            ->where('trader_id', $user->id)
            ->whereIn('status', [
                PayoutStatus::TAKEN->value,
                PayoutStatus::SENT->value,
            ])
            ->exists();

        if ($hasActivePayout) {
            return redirect()->back()->with('error', 'Нельзя архивировать трейдера с активной выплатой.');
        }

        Transaction::run(function () use ($user) {
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $user->paymentDetails()->where('is_active', true)->update([
                'is_active' => false,
            ]);

            $user->update([
                'archived_at' => now(),
                'stop_traffic' => true,
                'is_online' => false,
                'payouts_enabled' => false,
            ]);
        });

        return redirect()->back()->with('message', 'Пользователь отправлен в архив.');
    }

    public function unarchive(User $user)
    {
        if (! $user->hasRole('Trader')) {
            return redirect()->back()->with('error', 'Вернуть из архива можно только трейдера.');
        }

        if ($user->archived_at === null) {
            return redirect()->back()->with('error', 'Пользователь уже не в архиве.');
        }

        Transaction::run(function () use ($user) {
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $user->update([
                'archived_at' => null,
                'is_online' => false,
            ]);
        });

        return redirect()->back()->with('message', 'Пользователь возвращен из архива.');
    }

    public function reset2fa(User $user)
    {
        $user->update([
            'google2fa_secret' => null,
        ]);

        return redirect()->back()->with('success', 'Двухфакторная аутентификация успешно сброшена');
    }

    public function tempVipHistory(User $user)
    {
        $history = $user->tempVipActivations()
            ->orderByDesc('activated_at')
            ->get(['activated_at', 'expires_at'])
            ->map(fn ($item) => [
                'activated_at' => $item->activated_at?->toDateTimeString(),
                'expires_at' => $item->expires_at?->toDateTimeString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}
