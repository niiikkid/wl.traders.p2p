<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserOnlineController extends Controller
{
    public function toggle(Request $request)
    {
        $user = $request->user();

        // Если stop_traffic включен, не даем включить is_online
        if ($user->stop_traffic && !$user->is_online) {
            return;
        }

        $user->update(['is_online' => !$user->is_online]);
    }
}
