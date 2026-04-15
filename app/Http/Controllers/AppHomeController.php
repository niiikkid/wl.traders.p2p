<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Точка входа кабинета при разнесении лендинга (платёжный домен) и приложения (APP_URL).
 */
class AppHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $authenticatedHome = LandingPageController::resolveAuthenticatedHome($request);

        return $authenticatedHome ?? redirect()->route('login');
    }
}
