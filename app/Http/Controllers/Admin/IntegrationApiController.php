<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\IntegrationInfrastructureApiToken;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationApiController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/IntegrationApi/Index', [
            'token' => IntegrationInfrastructureApiToken::get(),
        ]);
    }

    public function regenerateToken(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'token' => IntegrationInfrastructureApiToken::regenerate(),
            ],
        ]);
    }
}
