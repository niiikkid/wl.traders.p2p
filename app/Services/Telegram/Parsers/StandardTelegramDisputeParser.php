<?php

declare(strict_types=1);

namespace App\Services\Telegram\Parsers;

use App\Contracts\TelegramChatFileServiceContract;
use App\Contracts\TelegramChatMessageParserContract;
use App\Enums\TelegramChatMessageStatus;
use App\Enums\TelegramChatParserType;
use App\Exceptions\DisputeException;
use App\Exceptions\TelegramChatBotException;
use App\Models\Order;
use App\Models\TelegramChatMessage;
use Illuminate\Support\Facades\Log;

class StandardTelegramDisputeParser implements TelegramChatMessageParserContract
{
    private const UUID_PATTERN = '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/';

    public function __construct(
        private readonly TelegramChatFileServiceContract $fileService,
    ) {}

    public function supports(TelegramChatParserType $parserType): bool
    {
        return $parserType->equals(TelegramChatParserType::STANDARD_DISPUTE);
    }

    public function process(TelegramChatMessage $message): void
    {
        $message = $message->fresh(['telegramChat']);

        if ($message === null || ! $message->status->equals(TelegramChatMessageStatus::RECEIVED)) {
            return;
        }

        $telegramChat = $message->telegramChat;

        if ($telegramChat === null) {
            return;
        }

        $messageText = $this->messageText($message);
        $uuidCandidates = $this->extractUuidCandidates($messageText);

        if ($uuidCandidates === []) {
            if ($telegramChat->debug_enabled) {
                $this->markMessage($message, TelegramChatMessageStatus::IGNORED);

                return;
            }

            $this->markMessage($message, TelegramChatMessageStatus::FAILED, 'UUID заказа не найден в сообщении.');

            return;
        }

        $orders = Order::query()
            ->with('dispute')
            ->whereIn('uuid', $uuidCandidates)
            ->get();

        if ($orders->isEmpty()) {
            $this->markMessage($message, TelegramChatMessageStatus::FAILED, 'UUID заказа не найден.');

            return;
        }

        if ($orders->count() > 1) {
            $this->markMessage($message, TelegramChatMessageStatus::FAILED, 'Найдено несколько заказов с подходящими UUID.');

            return;
        }

        $order = $orders->first();
        $rawPayload = $message->raw_payload;

        if (! is_array($rawPayload)) {
            $this->markMessage($message, TelegramChatMessageStatus::FAILED, 'Отсутствуют данные сообщения Telegram.');

            return;
        }

        $attachmentReference = $this->fileService->extractAttachmentReference($rawPayload);

        if ($attachmentReference === null) {
            $this->markMessage($message, TelegramChatMessageStatus::FAILED, 'Чек (фото или документ) не найден в сообщении.');

            return;
        }

        $message->update([
            'detected_uuid' => $order->uuid,
            'order_id' => $order->id,
            'status' => TelegramChatMessageStatus::MATCHED,
        ]);

        if ($order->dispute !== null) {
            $this->markMessage($message, TelegramChatMessageStatus::DUPLICATE, orderId: $order->id);

            return;
        }

        try {
            $attachment = $this->fileService->downloadAndStore($message, $attachmentReference);
            $uploadedFile = $this->fileService->toUploadedFile($attachment);
            $dispute = services()->dispute()->create($order->id, $uploadedFile);

            $message->update([
                'status' => TelegramChatMessageStatus::PROCESSED,
                'dispute_id' => $dispute->id,
                'order_id' => $order->id,
                'detected_uuid' => $order->uuid,
                'failure_reason' => null,
                'processed_at' => now(),
            ]);

            $this->sendSuccessReply($telegramChat->telegram_chat_id, $order->uuid);
        } catch (DisputeException $exception) {
            if (str_contains($exception->getMessage(), 'already exists')) {
                $this->markMessage($message, TelegramChatMessageStatus::DUPLICATE, orderId: $order->id);

                return;
            }

            $this->markMessage($message, TelegramChatMessageStatus::FAILED, $exception->getMessage(), $order->id);
        } catch (TelegramChatBotException $exception) {
            $this->markMessage($message, TelegramChatMessageStatus::FAILED, $exception->getMessage(), $order->id);
        } catch (\Throwable $exception) {
            Log::warning('Telegram chat dispute message processing failed', [
                'telegram_chat_message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $this->markMessage($message, TelegramChatMessageStatus::FAILED, 'Ошибка обработки сообщения.', $order->id);
        }
    }

    /**
     * @return list<string>
     */
    private function extractUuidCandidates(?string $messageText): array
    {
        if ($messageText === null || $messageText === '') {
            return [];
        }

        if (preg_match_all(self::UUID_PATTERN, $messageText, $matches) < 1) {
            return [];
        }

        $candidates = [];

        foreach ($matches[0] as $match) {
            if (! is_string($match)) {
                continue;
            }

            $normalized = strtolower($match);
            $candidates[$normalized] = $normalized;
        }

        return array_values($candidates);
    }

    private function messageText(TelegramChatMessage $message): ?string
    {
        if (is_string($message->text) && $message->text !== '') {
            return $message->text;
        }

        if (is_string($message->caption) && $message->caption !== '') {
            return $message->caption;
        }

        return null;
    }

    private function sendSuccessReply(string $apiChatId, string $orderUuid): void
    {
        $text = "Спор открыт.\nUUID сделки: {$orderUuid}";

        try {
            services()->telegramChatBot()->sendChatMessage($apiChatId, $text);
        } catch (TelegramChatBotException $exception) {
            Log::warning('Telegram dispute success reply failed', [
                'telegram_chat_id' => $apiChatId,
                'order_uuid' => $orderUuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function markMessage(
        TelegramChatMessage $message,
        TelegramChatMessageStatus $status,
        ?string $failureReason = null,
        ?int $orderId = null,
    ): void {
        $data = [
            'status' => $status,
            'failure_reason' => $failureReason,
            'processed_at' => now(),
        ];

        if ($orderId !== null) {
            $data['order_id'] = $orderId;
        }

        $message->update($data);
    }
}
