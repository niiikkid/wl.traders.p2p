<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApiIntegrationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $tokenUser = $user;

        if ($user->hasRole('Merchant Support') && $user->merchant) {
            $tokenUser = $user->merchant;
        }

        $token = $tokenUser->api_access_token;

        if ($user->hasRole('Merchant Support')) {
            $merchants = $user->merchants()
                ->orderByDesc('merchants.id')
                ->get();
        } else {
            $merchants = Merchant::query()
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('supports', function ($supportsQuery) use ($user) {
                            $supportsQuery->where('users.id', $user->id);
                        });
                })
                ->orderByDesc('id')
                ->get();
        }

        $merchantList = $merchants->map(static function (Merchant $merchant) {
            return [
                'uuid' => $merchant->uuid,
                'name' => $merchant->name,
            ];
        })->values();

        $firstMerchant = $merchantList->first() ?? [];
        $merchantId = $firstMerchant['uuid'] ?? null;

        return Inertia::render('Integration/Index', [
            'token' => $token,
            'merchantId' => $merchantId,
            'merchants' => $merchantList,
        ]);
    }

    public function receiptTemplate(): JsonResponse
    {
        $path = base_path('example_check.png');

        if (!file_exists($path)) {
            return response()->json([
                'message' => 'Пример чека недоступен',
            ], 404);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return response()->json([
                'message' => 'Не удалось прочитать файл чека',
            ], 500);
        }

        return response()->json([
            'data' => [
                'base64' => base64_encode($contents),
            ],
        ]);
    }

    public function regenerateToken(): JsonResponse
    {
        $user = auth()->user();
        $tokenUser = $user;

        if ($user->hasRole('Merchant Support')) {
            if (! $user->merchant) {
                return response()->json([
                    'message' => 'Мерчант не найден',
                ], 404);
            }

            $tokenUser = $user->merchant;
        }

        $token = $this->generateApiAccessToken();

        $tokenUser->update([
            'api_access_token' => $token,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
            ],
        ]);
    }

    private function generateApiAccessToken(): string
    {
        do {
            $token = strtolower(Str::random(32));
        } while (User::query()->where('api_access_token', $token)->exists());

        return $token;
    }
}
