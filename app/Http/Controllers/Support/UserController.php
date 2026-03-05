<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();

        $users = User::query()
            ->with(['roles', 'wallet'])
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('email', 'like', '%' . $filters->user . '%');
                    $query->orWhere('name', 'like', '%' . $filters->user . '%');
                });
            })
            ->when($filters->online, function ($query) use ($filters) {
                $query->where('is_online', true);
            })
            ->when($filters->traffic_disabled, function ($query) use ($filters) {
                $query->where('stop_traffic', true);
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $users = UserResource::collection($users);

        return Inertia::render('Support/User/Index', compact('users', 'filters'));
    }

    public function toggleTraffic(Request $request, User $user)
    {
        $stopTraffic = !$user->stop_traffic;

        $data = [
            'stop_traffic' => $stopTraffic
        ];

        // Если включаем трафик, устанавливаем время включения
        if (!$stopTraffic) {
            $data['traffic_enabled_at'] = now();
        }

        $user->update($data);

        return redirect()->back();
    }
}
