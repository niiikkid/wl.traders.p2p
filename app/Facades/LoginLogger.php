<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void disable()
 * @method static void enable()
 * @method static bool isEnabled()
 * 
 * @see \App\Support\LoginLogger
 */
class LoginLogger extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'login-logger';
    }
} 