<?php

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\NotificationController as BaseNotificationController;
use Illuminate\Http\Request;

class NotificationController extends BaseNotificationController
{
    public function index(Request $request)
    {
        return $this->renderIndex($request, 'Notifications/Index');
    }
}
