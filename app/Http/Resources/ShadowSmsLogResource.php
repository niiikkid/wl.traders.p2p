<?php

namespace App\Http\Resources;

use App\Enums\ShadowSmsLogFilterReason;
use App\Models\ShadowSmsLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShadowSmsLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var ShadowSmsLog $this
         */
        $filterReason = $this->filter_reason;

        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'message' => $this->message,
            'timestamp' => $this->formatTimestamp(),
            'type' => $this->type->value,
            'filter_reason' => $filterReason->value,
            'filter_reason_label' => $filterReason->label(),
            'filter_detail_text' => $this->filterDetailText($filterReason),
            'matched_sender' => $this->matched_sender,
            'matched_stop_word' => $this->matched_stop_word,
            'message_length' => $this->message_length,
            'created_at' => $this->created_at->toDateTimeString(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                ];
            }),
            'device' => $this->whenLoaded('device', function () {
                return [
                    'id' => $this->device->id,
                    'name' => $this->device->name,
                ];
            }),
        ];
    }

    private function formatTimestamp(): string
    {
        $timestamp = $this->timestamp > 9999999999
            ? (int) floor($this->timestamp / 1000)
            : $this->timestamp;

        return Carbon::createFromTimestamp($timestamp)->toDateTimeString();
    }

    private function filterDetailText(ShadowSmsLogFilterReason $filterReason): string
    {
        return match ($filterReason) {
            ShadowSmsLogFilterReason::SenderStopList => 'Отправитель: '.($this->matched_sender ?? 'не указан'),
            ShadowSmsLogFilterReason::StopWord => 'Слово: '.($this->matched_stop_word ?? 'не указано'),
            ShadowSmsLogFilterReason::MaxMessageLength => 'Длина: '.($this->message_length ?? 'не указана').' (лимит 200)',
        };
    }
}
