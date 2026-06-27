<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ApiIntegrationController extends Controller
{
    public function index()
    {
        return Inertia::render('Integration/Index', $this->integrationPageProps());
    }

    /**
     * @return array{hasApiToken: bool, hasWebhookSecret: bool}
     */
    private function integrationPageProps(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return [
            'hasApiToken' => filled($user->api_access_token_hash),
            'hasWebhookSecret' => filled($user->webhook_secret),
        ];
    }

    public function regenerateToken(): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }
        $token = $user->rotateApiAccessToken();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
            ],
        ]);
    }

    public function regenerateWebhookSecret(): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $secret = $user->rotateWebhookSecret();

        return response()->json([
            'success' => true,
            'data' => [
                'webhook_secret' => $secret,
            ],
        ]);
    }
}
