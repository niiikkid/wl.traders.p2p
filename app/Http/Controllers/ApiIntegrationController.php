<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApiIntegrationController extends Controller
{
    public function index()
    {
        return Inertia::render('Integration/Index', $this->integrationPageProps());
    }

    /**
     * @return array{token: string|null}
     */
    private function integrationPageProps(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return [
            'token' => $user->api_access_token,
        ];
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
