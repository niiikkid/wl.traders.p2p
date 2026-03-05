<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsStopWord;
use Illuminate\Http\Request;

class SmsStopWordController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
        ]);

        SmsStopWord::create([
            'word' => $validated['word']
        ]);
    }

    public function destroy(SmsStopWord $smsStopWord)
    {
        $smsStopWord->delete();
    }
}
