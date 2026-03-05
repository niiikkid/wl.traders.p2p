<?php

namespace App\Http\Controllers;

class TelegramSettingsController extends Controller
{
    public function refreshLink()
    {
        services()->telegram()->refreshLink(auth()->user());

        return back();
    }

    public function unlink()
    {
        services()->telegram()->refreshLink(auth()->user());

        return back();
    }
}
