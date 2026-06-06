<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramChatBotServiceContract;
use App\Enums\DisputeStatus;
use App\Enums\TelegramChatStatus;
use App\Enums\TelegramChatType;
use App\Enums\TelegramTraderTeamDisputeNotificationType;
use App\Exceptions\TelegramChatBotException;
use App\Models\Dispute;
use App\Models\TelegramChat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramTraderTeamDisputeNotificationService
{
    /**
     * @return Collection<int, TelegramChat>
     */
    public function findActiveTeamChatsForTrader(int $traderId): Collection
    {
        return TelegramChat::query()
            ->where('chat_type', TelegramChatType::TRADER_TEAM)
            ->where('status', TelegramChatStatus::ACTIVE)
            ->whereHas('traders', fn ($query) => $query->where('users.id', $traderId))
            ->with(['traders' => fn ($query) => $query->where('users.id', $traderId)])
            ->get();
    }

    public function isDisputePending(Dispute $dispute): bool
    {
        return $dispute->status->equals(DisputeStatus::PENDING);
    }

    public function sendNotifications(
        Dispute $dispute,
        TelegramTraderTeamDisputeNotificationType $type,
        TelegramChatBotServiceContract $telegramChatBot,
    ): void {
        $chats = $this->findActiveTeamChatsForTrader($dispute->trader_id);

        if ($chats->isEmpty()) {
            return;
        }

        $orderUuid = $dispute->order?->uuid;

        if (! is_string($orderUuid) || $orderUuid === '') {
            return;
        }

        foreach ($chats as $chat) {
            try {
                $mention = $this->resolveMention($chat, $dispute->trader_id);
                $text = $this->buildMessage($type, $mention, $orderUuid);

                $telegramChatBot->sendChatMessage($chat->telegram_chat_id, $text);
            } catch (TelegramChatBotException $exception) {
                $this->logNotificationFailure($dispute, $chat, $type, $exception);
            } catch (Throwable $exception) {
                $this->logNotificationFailure($dispute, $chat, $type, $exception);
            }
        }
    }

    private function resolveMention(TelegramChat $chat, int $traderId): string
    {
        $trader = $chat->traders->firstWhere('id', $traderId);
        $username = $trader?->pivot?->telegram_username;

        if (! is_string($username) || $username === '') {
            return '';
        }

        return '@'.$username.' ';
    }

    private function buildMessage(
        TelegramTraderTeamDisputeNotificationType $type,
        string $mention,
        string $orderUuid,
    ): string {
        return match ($type) {
            TelegramTraderTeamDisputeNotificationType::IMMEDIATE => "{$mention}Открыт новый спор.\nUUID сделки: {$orderUuid}\nПожалуйста, обработайте спор.",
            TelegramTraderTeamDisputeNotificationType::FIFTEEN_MINUTE_REMINDER => "{$mention}Спор всё ещё ожидает обработки.\nUUID сделки: {$orderUuid}\nПожалуйста, обработайте его как можно скорее.",
            TelegramTraderTeamDisputeNotificationType::HOURLY_REMINDER => "{$mention}Напоминание: спор всё ещё открыт.\nUUID сделки: {$orderUuid}\nТребуется обработка спора.",
        };
    }

    private function logNotificationFailure(
        Dispute $dispute,
        TelegramChat $chat,
        TelegramTraderTeamDisputeNotificationType $type,
        Throwable $exception,
    ): void {
        Log::warning('Telegram trader team dispute notification failed', [
            'dispute_id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'order_uuid' => $dispute->order?->uuid,
            'trader_id' => $dispute->trader_id,
            'telegram_chat_id' => $chat->id,
            'api_telegram_chat_id' => $chat->telegram_chat_id,
            'notification_type' => $type->value,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
