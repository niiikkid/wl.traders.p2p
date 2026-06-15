<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entry point for the back office root page.
 */
class AppHomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->hasRole('Merchant')) {
            return redirect()->route('merchant.main.index');
        }
        if ($user->hasRole('Trader')) {
            return redirect()->route('trader.main.index');
        }
        if ($user->hasRole('Support')) {
            return redirect()->route('support.users.index');
        }
        if ($user->hasRole('Team Leader')) {
            return redirect()->route('leader.main.index');
        }

        return redirect()->route('admin.main.index');
    }
}
