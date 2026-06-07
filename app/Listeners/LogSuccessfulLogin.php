<?php

namespace App\Listeners;

use App\Facades\LoginLogger;
use Illuminate\Auth\Events\Login;

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
        if (! LoginLogger::isEnabled()) {
            return;
        }

        if (! services()->loginHistory()->isLoggingEnabledFor($event->user)) {
            return;
        }

        services()->loginHistory()->recordLogin($event->user, request());
    }
}
