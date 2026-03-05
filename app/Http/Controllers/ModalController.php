<?php

namespace App\Http\Controllers;

use App\Http\Resources\SmsLogResource;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ModalController extends Controller
{
    public function smsLogs(User $user)
    {
        Gate::authorize('access-to-self', $user);

        $smsLogs = SmsLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('parsing_result')
            ->with('user')
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
        $smsLogs = SmsLogResource::collection($smsLogs);

        return response()->success($smsLogs);
    }
}
