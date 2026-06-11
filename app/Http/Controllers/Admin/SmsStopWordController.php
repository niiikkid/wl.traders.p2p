<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsStopWord;
use App\Services\Sms\SmsStopWordService;
use App\Services\Sms\Utils\NormalizeMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SmsStopWordController extends Controller
{
    private const STOP_WORDS_CACHE_KEY = 'sms_stop_words';

    public function store(Request $request, SmsStopWordService $smsStopWordService)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
        ]);

        $normalizedWord = NormalizeMessage::normalize(trim($validated['word']));

        SmsStopWord::create([
            'word' => $normalizedWord,
        ]);

        Cache::forget(self::STOP_WORDS_CACHE_KEY);

        $smsStopWordService->deleteUndefinedOperationLogsMatchingWord($normalizedWord);
    }

    public function destroy(SmsStopWord $smsStopWord)
    {
        SmsStopWord::query()->whereKey($smsStopWord->getKey())->delete();

        Cache::forget(self::STOP_WORDS_CACHE_KEY);
    }
}
