<?php

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\NotificationController as BaseNotificationController;
use App\Http\Requests\NotificationFilterRequest;

class NotificationController extends BaseNotificationController
{
    public function index(NotificationFilterRequest $request)
    {
        return $this->renderIndex($request, 'Notifications/Index');
    }
}
