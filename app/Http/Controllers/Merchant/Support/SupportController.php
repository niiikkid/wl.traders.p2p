<?php

namespace App\Http\Controllers\Merchant\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\Support\StoreSupportRequest;
use App\Http\Requests\Merchant\Support\UpdateSupportRequest;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\UserResource;
use App\Models\Merchant;
use App\Models\User;
use App\Utils\Transaction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index()
    {
        $merchant = auth()->user();

        // Получаем всех саппортов текущего мерчанта
        $supports = User::query()
            ->with('roles')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Merchant Support');
            })
            ->where('merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->paginate(10);

        $supports = UserResource::collection($supports);

        return Inertia::render('Merchant/Support/Index', compact('supports'));
    }

    public function createData()
    {
        // Получаем все магазины текущего пользователя
        $merchants = Merchant::query()
            ->where('user_id', auth()->user()->id)
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->orderByDesc('id')
            ->get();

        $merchants = $merchants->map(function ($merchant) {
            return [
                'id' => $merchant->id,
                'label' => $merchant->name,
                'value' => $merchant->id
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'merchants' => $merchants,
            ],
        ]);
    }

    public function store(StoreSupportRequest $request)
    {
        Transaction::run(function () use ($request) {
            $merchant = auth()->user();
            $merchantSupportRole = Role::where('name', 'Merchant Support')->first();

            if (!$merchantSupportRole) {
                return redirect()->back()->with('error', 'Роль Merchant Support не найдена');
            }

            $user = User::create([
                'name' => '',
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
                'apk_access_token' => strtolower(Str::random(32)),
                'api_access_token' => strtolower(Str::random(32)),
                'avatar_uuid' => strtolower($request->email),
                'avatar_style' => 'adventurer',
                'merchant_id' => $merchant->id, // Привязываем саппорта к мерчанту
                'traffic_enabled_at' => now(),
            ]);

            $user->assignRole($merchantSupportRole);

            // Привязываем саппорта к выбранным магазинам
            if (!empty($request->merchant_ids)) {
                $user->merchants()->sync($request->merchant_ids);
            }

            services()->wallet()->create($user);
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }
        return redirect()->route('merchant.support.index');
    }

    public function editData(User $support)
    {
        // Проверяем, что саппорт принадлежит текущему мерчанту
        $this->checkSupportOwnership($support);

        $support->load('roles', 'merchants');
        $supportMerchantIds = $support->merchants->pluck('id')->toArray();

        // Получаем все магазины текущего пользователя
        $merchants = Merchant::query()
            ->where('user_id', auth()->user()->id)
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->orderByDesc('id')
            ->get();

        $merchants = $merchants->map(function ($merchant) {
            return [
                'id' => $merchant->id,
                'label' => $merchant->name,
                'value' => $merchant->id
            ];
        });

        $support = UserResource::make($support)->resolve();

        return response()->json([
            'success' => true,
            'data' => [
                'support' => $support,
                'merchants' => $merchants,
                'supportMerchantIds' => $supportMerchantIds,
            ],
        ]);
    }

    public function update(UpdateSupportRequest $request, User $support)
    {
        // Проверяем, что саппорт принадлежит текущему мерчанту
        $this->checkSupportOwnership($support);

        Transaction::run(function () use ($request, $support) {
            $support->update([
                'name' => '',
                'email' => strtolower($request->email),
                'banned_at' => $request->banned ? now() : null,
            ]);

            // Обновляем связи с магазинами
            if (isset($request->merchant_ids)) {
                $support->merchants()->sync($request->merchant_ids);
            }
        });

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }
        return redirect()->route('merchant.support.index');
    }

    /**
     * Проверка, что саппорт принадлежит текущему мерчанту
     */
    private function checkSupportOwnership(User $support)
    {
        $merchant = auth()->user();

        if ($support->merchant_id !== $merchant->id || !$support->hasRole('Merchant Support')) {
            abort(403, 'У вас нет прав на управление этим саппортом');
        }
    }
}
