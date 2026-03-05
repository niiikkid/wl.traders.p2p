<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Facades\LoginLogger;

class LogSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // Проверяем глобальный флаг через фасад
        if (!LoginLogger::isEnabled()) {
            return;
        }

        services()->loginHistory()->recordLogin($event->user, request());
    }
}
