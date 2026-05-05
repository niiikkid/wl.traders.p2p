<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApiIntegrationController extends Controller
{
    public function index()
    {
        return Inertia::render('Integration/Index', $this->integrationPageProps());
    }

    public function v2()
    {
        $user = Auth::user();
        if ($user instanceof User && $user->hasRole('Merchant') && ! $user->hasRole('Super Admin')) {
            abort(404);
        }

        return Inertia::render('Integration/V2');
    }

    /**
     * @return array{token: mixed, merchantId: mixed, merchants: Collection<int, array{uuid: string, name: string}>}
     */
    private function integrationPageProps(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }
        $token = $user->api_access_token;

        $merchants = Merchant::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $merchantList = $merchants->map(static function (Merchant $merchant) {
            return [
                'uuid' => $merchant->uuid,
                'name' => $merchant->name,
            ];
        })->values();

        $firstMerchant = $merchantList->first() ?? [];
        $merchantId = $firstMerchant['uuid'] ?? null;

        return [
            'token' => $token,
            'merchantId' => $merchantId,
            'merchants' => $merchantList,
        ];
    }

    public function receiptTemplate(): JsonResponse
    {
        $path = base_path('example_check.png');

        if (! file_exists($path)) {
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
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }
        $token = $this->generateApiAccessToken();

        $user->update([
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
