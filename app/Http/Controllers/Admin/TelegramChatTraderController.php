<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TelegramChat\SearchTraderRequest;
use App\Http\Requests\Admin\TelegramChat\StoreTraderRequest;
use App\Http\Requests\Admin\TelegramChat\UpdateTraderRequest;
use App\Models\TelegramChat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TelegramChatTraderController extends Controller
{
    public function search(SearchTraderRequest $request): JsonResponse
    {
        $search = trim((string) ($request->validated('query') ?? ''));

        $query = User::query()
            ->role('Trader')
            ->select(['id', 'email'])
            ->orderBy('email')
            ->limit(20);

        if ($search !== '') {
            $query->where('email', 'like', '%'.$search.'%');
        }

        $traders = $query
            ->get()
            ->map(fn (User $trader) => [
                'id' => $trader->id,
                'email' => $trader->email,
            ])
            ->values()
            ->all();

        return response()->json([
            'traders' => $traders,
        ]);
    }

    public function store(StoreTraderRequest $request, TelegramChat $telegramChat): RedirectResponse
    {
        $validated = $request->validated();

        $telegramChat->traders()->attach($validated['trader_id'], [
            'telegram_username' => $validated['telegram_username'] ?? null,
        ]);

        return redirect()
            ->back()
            ->with('message', 'Трейдер добавлен в команду чата.');
    }

    public function update(UpdateTraderRequest $request, TelegramChat $telegramChat, User $trader): RedirectResponse
    {
        if (! $telegramChat->traders()->whereKey($trader->id)->exists()) {
            abort(404);
        }

        $validated = $request->validated();

        $telegramChat->traders()->updateExistingPivot($trader->id, [
            'telegram_username' => $validated['telegram_username'] ?? null,
        ]);

        return redirect()
            ->back()
            ->with('message', 'Данные участника команды обновлены.');
    }

    public function destroy(TelegramChat $telegramChat, User $trader): RedirectResponse
    {
        if (! $telegramChat->traders()->whereKey($trader->id)->exists()) {
            abort(404);
        }

        $telegramChat->traders()->detach($trader->id);

        return redirect()
            ->back()
            ->with('message', 'Трейдер удалён из команды чата.');
    }
}
