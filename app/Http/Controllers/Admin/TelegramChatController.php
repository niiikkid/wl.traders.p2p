<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TelegramChatStatus;
use App\Exceptions\TelegramChatBotException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TelegramChat\ToggleDebugRequest;
use App\Http\Requests\Admin\TelegramChat\UpdateRequest;
use App\Http\Resources\TelegramBotSettingResource;
use App\Http\Resources\TelegramChatMessageResource;
use App\Http\Resources\TelegramChatResource;
use App\Jobs\CleanupTelegramChatDebugMessagesJob;
use App\Models\TelegramChat;
use App\Models\TelegramChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TelegramChatController extends Controller
{
    public function index(): Response
    {
        $fromArchive = request()->tab === 'archived';
        $selectedChatId = request()->integer('chat') ?: null;

        $chats = TelegramChat::query()
            ->withCount('messages')
            ->with('latestMessage')
            ->when($fromArchive, function ($query) {
                $query->where('status', TelegramChatStatus::ARCHIVED);
            }, function ($query) {
                $query->where('status', '!=', TelegramChatStatus::ARCHIVED);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(request()->integer('per_page') ?: 10)
            ->withQueryString();

        $botSetting = services()->telegramChatBot()->getSettings();

        if ($botSetting->hasBotToken()) {
            try {
                $botSetting = services()->telegramChatBot()->refreshWebhookMetadata();
            } catch (TelegramChatBotException) {
                // Keep stored metadata when Telegram API is temporarily unavailable.
            }
        }

        $selectedChat = null;
        $messages = null;

        if ($selectedChatId > 0) {
            $selectedChat = TelegramChat::query()
                ->withCount('messages')
                ->with('latestMessage')
                ->find($selectedChatId);

            if ($selectedChat !== null) {
                $messages = TelegramChatMessage::query()
                    ->where('telegram_chat_id', $selectedChat->id)
                    ->with(['order', 'dispute', 'attachments.telegramChatMessage.telegramChat'])
                    ->orderByDesc('id')
                    ->paginate(request()->integer('messages_per_page') ?: 15, ['*'], 'messages_page')
                    ->withQueryString();
            }
        }

        return Inertia::render('Admin/TelegramChats/Index', [
            'chats' => TelegramChatResource::collection($chats),
            'botSetting' => TelegramBotSettingResource::make($botSetting)->resolve(),
            'selectedChat' => $selectedChat
                ? TelegramChatResource::make($selectedChat)->resolve()
                : null,
            'messages' => $messages
                ? TelegramChatMessageResource::collection($messages)
                : null,
            'messagesMeta' => $messages ? [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ] : null,
            'tab' => $fromArchive ? 'archived' : 'active',
            'parserTypes' => [
                ['value' => 'standard_dispute', 'label' => 'Стандартный (спор)'],
            ],
            'chatStatuses' => [
                ['value' => 'pending_moderation', 'label' => 'Ожидает модерации'],
                ['value' => 'active', 'label' => 'Активен'],
                ['value' => 'disabled', 'label' => 'Отключён'],
                ['value' => 'archived', 'label' => 'В архиве'],
            ],
        ]);
    }

    public function messages(TelegramChat $telegramChat): JsonResponse
    {
        $messages = TelegramChatMessage::query()
            ->where('telegram_chat_id', $telegramChat->id)
            ->with(['order', 'dispute', 'attachments.telegramChatMessage.telegramChat'])
            ->orderByDesc('id')
            ->paginate(request()->integer('per_page') ?: 15);

        return response()->json([
            'messages' => TelegramChatMessageResource::collection($messages)->resolve(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function update(UpdateRequest $request, TelegramChat $telegramChat): RedirectResponse
    {
        $previousStatus = $telegramChat->status;
        $validated = $request->validated();

        $telegramChat->update($validated);
        $telegramChat->refresh();

        $message = 'Настройки чата обновлены.';

        if (
            array_key_exists('status', $validated)
            && $telegramChat->status->equals(TelegramChatStatus::ACTIVE)
            && ! $previousStatus->equals(TelegramChatStatus::ACTIVE)
        ) {
            $redispatched = $telegramChat->redispatchReceivedMessages();

            if ($redispatched > 0) {
                $message .= " Повторно поставлено в очередь сообщений: {$redispatched}.";
            }
        }

        return redirect()
            ->back()
            ->with('message', $message);
    }

    public function archive(TelegramChat $telegramChat): RedirectResponse
    {
        $telegramChat->update([
            'status' => TelegramChatStatus::ARCHIVED,
        ]);

        return redirect()
            ->back()
            ->with('message', 'Чат перемещён в архив.');
    }

    public function restore(TelegramChat $telegramChat): RedirectResponse
    {
        $telegramChat->update([
            'status' => TelegramChatStatus::PENDING_MODERATION,
        ]);

        return redirect()
            ->back()
            ->with('message', 'Чат восстановлен из архива.');
    }

    public function toggleDebug(ToggleDebugRequest $request, TelegramChat $telegramChat): RedirectResponse
    {
        $validated = $request->validated();
        $wasDebugEnabled = $telegramChat->debug_enabled;
        $debugEnabled = (bool) $validated['debug_enabled'];

        $telegramChat->update([
            'debug_enabled' => $debugEnabled,
        ]);

        if ($wasDebugEnabled && ! $debugEnabled) {
            CleanupTelegramChatDebugMessagesJob::dispatch($telegramChat);
        }

        $message = $debugEnabled
            ? 'Режим отладки включён для чата.'
            : 'Режим отладки выключен. Запущена очистка накопленных debug-сообщений.';

        return redirect()
            ->back()
            ->with('message', $message);
    }
}
