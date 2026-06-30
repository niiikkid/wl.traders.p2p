<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UserOnline\UserOnlinePingRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlinePingController extends Controller
{
    /**
     * Принять онлайн-пинг веб-панели для любого авторизованного пользователя.
     * Запись дедуплицируется до шага 15 секунд внутри рекордера.
     */
    public function store(Request $request, UserOnlinePingRecorder $recorder): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $recorder->record($user->id);
        }

        return response()->success();
    }
}
