<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ManualControlConfirmationType;
use App\Enums\ManualControlProcessingStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderManualControlConfirmationCode;
use App\Models\UserMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualControlAcqController extends Controller
{
    private const HISTORY_DISPLAY_LIMIT = 5;
    private const DEFAULT_NEW_OFFER_SOUND_TRACK = 'radwimps.mp3';
    private const DEFAULT_CONFIRM_CODE_SOUND_TRACK = 'LetWealthCome.mp3';
    private const PRIORITY_SOUND_TRACKS = [
        'DreamsAreMessagesFromTheDeep.mp3',
        'LetWealthCome.mp3',
        'Loshadka-1.mp3',
        'Loshadka-2.mp3',
        'MoneyPowerWomanDrugs.mp3',
        'Pressure.mp3',
        'SixDays.mp3',
    ];

    public function show(): Response
    {
        $audio_tracks = $this->getAudioTracks();
        $sound_settings = $this->resolveSoundSettings(auth()->user()?->meta, $audio_tracks);

        return Inertia::render('Admin/ManualControlAcq/Show', [
            'audioTracks' => $audio_tracks,
            'soundSettings' => $sound_settings,
        ]);
    }

    public function state(): JsonResponse
    {
        if (! $this->isCurrentUserWorking()) {
            return response()->success([
                'is_working' => false,
                'incoming_offer' => null,
                'incoming_queue_waiting_count' => 0,
                'active_queue_items' => [],
                'history_queue_items' => [],
                'history_total_count' => 0,
            ]);
        }

        $active_orders = Order::query()
            ->with([
                'paymentGateway',
                'manualControlConfirmationCodes' => fn ($query) => $query->orderByDesc('id'),
            ])
            ->where('manual_control_acquiring', true)
            ->where('status', OrderStatus::PENDING)
            ->where(function ($query) {
                $query
                    ->where('manual_control_processing_status', ManualControlProcessingStatus::PENDING)
                    ->orWhereNull('manual_control_processing_status');
            })
            ->where('manual_control_taken_by_user_id', auth()->id())
            ->orderByDesc('manual_control_taken_at')
            ->orderBy('id')
            ->get();

        $incoming_order_query = Order::query()
            ->with('paymentGateway')
            ->where('manual_control_acquiring', true)
            ->where('status', OrderStatus::PENDING)
            ->where(function ($query) {
                $query
                    ->where('manual_control_processing_status', ManualControlProcessingStatus::PENDING)
                    ->orWhereNull('manual_control_processing_status');
            })
            ->whereNull('manual_control_taken_by_user_id')
            ->orderBy('created_at');
        $incoming_order = (clone $incoming_order_query)->first();
        $incoming_queue_total_count = (clone $incoming_order_query)->count();

        $history_orders_query = Order::query()
            ->with([
                'paymentGateway',
                'manualControlConfirmationCodes' => fn ($query) => $query->orderByDesc('id'),
            ])
            ->where('manual_control_acquiring', true)
            ->where('manual_control_taken_by_user_id', auth()->id())
            ->whereNotNull('manual_control_taken_at')
            ->where(function ($query) {
                $query
                    ->whereIn('manual_control_processing_status', [
                        ManualControlProcessingStatus::REJECTED,
                        ManualControlProcessingStatus::CONFIRMED,
                    ])
                    ->orWhere(function ($legacy_query) {
                        $legacy_query
                            ->whereNull('manual_control_processing_status')
                            ->where('status', '!=', OrderStatus::PENDING);
                    });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
        $history_total_count = (clone $history_orders_query)->count();
        $history_orders = (clone $history_orders_query)
            ->limit(self::HISTORY_DISPLAY_LIMIT)
            ->get();

        return response()->success([
            'is_working' => true,
            'incoming_offer' => $incoming_order ? $this->makeIncomingOfferPreview($incoming_order) : null,
            'incoming_queue_waiting_count' => max(0, $incoming_queue_total_count - 1),
            'active_queue_items' => $this->makeQueueItems($active_orders),
            'history_queue_items' => $this->makeHistoryQueueItems($history_orders),
            'history_total_count' => $history_total_count,
        ]);
    }

    public function take(Order $order): JsonResponse
    {
        if (! $this->isCurrentUserWorking()) {
            return response()->failWithMessage('Режим работы выключен. Включите его, чтобы брать заявки.');
        }

        $lock = Cache::lock($this->makeTakeLockKey($order->id), 5);

        if (! $lock->get()) {
            return response()->failWithMessage('Эту заявку уже берет в обработку другой пользователь.');
        }

        try {
            $updated_rows = Order::query()
                ->whereKey($order->id)
                ->where('manual_control_acquiring', true)
                ->where('status', OrderStatus::PENDING)
                ->where(function ($query) {
                    $query
                        ->where('manual_control_processing_status', ManualControlProcessingStatus::PENDING)
                        ->orWhereNull('manual_control_processing_status');
                })
                ->whereNull('manual_control_taken_by_user_id')
                ->update([
                    'manual_control_taken_by_user_id' => auth()->id(),
                    'manual_control_taken_at' => now(),
                    'manual_control_processing_status' => ManualControlProcessingStatus::PENDING,
                ]);

            if ($updated_rows === 0) {
                return response()->failWithMessage('Заявку уже взял в обработку другой пользователь.');
            }
        } finally {
            $lock->release();
        }

        return $this->state();
    }

    public function reject(Request $request, Order $order): JsonResponse
    {
        if (! $this->isCurrentUserWorking()) {
            return response()->failWithMessage('Режим работы выключен. Включите его, чтобы отклонять заявки.');
        }

        $latest_order = Order::query()->whereKey($order->id)->firstOrFail();

        if (! $this->canRejectOrder($latest_order)) {
            return response()->failWithMessage('Заявка уже недоступна для отклонения.');
        }

        $validated_data = $request->validate([
            'reject_reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        try {
            DB::transaction(function () use ($latest_order, $validated_data): void {
                services()->order()->finishOrderAsFailed($latest_order->id, OrderSubStatus::CANCELED);

                Order::query()
                    ->whereKey($latest_order->id)
                    ->update([
                        'manual_control_processing_status' => ManualControlProcessingStatus::REJECTED,
                        'manual_control_reject_reason' => isset($validated_data['reject_reason'])
                            ? trim((string) $validated_data['reject_reason'])
                            : null,
                    ]);
            });
        } catch (OrderException $e) {
            return response()->failWithMessage($e->getMessage());
        }

        return $this->state();
    }

    public function confirm(Order $order): JsonResponse
    {
        if (! $this->isCurrentUserWorking()) {
            return response()->failWithMessage('Режим работы выключен. Включите его, чтобы подтверждать заявки.');
        }

        $latest_order = Order::query()->whereKey($order->id)->firstOrFail();

        if (! $this->canConfirmOrder($latest_order)) {
            return response()->failWithMessage('Заявка уже недоступна для подтверждения.');
        }

        $latest_order->update([
            'manual_control_processing_status' => ManualControlProcessingStatus::CONFIRMED,
        ]);

        return $this->state();
    }

    public function setConfirmationType(Request $request, Order $order): JsonResponse
    {
        if (! $this->isCurrentUserWorking()) {
            return response()->failWithMessage('Режим работы выключен. Включите его, чтобы выбрать тип подтверждения.');
        }

        $validated_data = $request->validate([
            'confirmation_type' => [
                'required',
                'string',
                Rule::in(ManualControlConfirmationType::values()),
            ],
        ]);

        $latest_order = Order::query()->whereKey($order->id)->firstOrFail();

        if (! $this->canSetConfirmationType($latest_order)) {
            return response()->failWithMessage('Нельзя установить тип подтверждения для этой заявки.');
        }

        $latest_order->update([
            'manual_control_confirmation_type' => $validated_data['confirmation_type'],
            'manual_control_confirmation_type_set_at' => now(),
            'manual_control_processing_status' => $latest_order->manual_control_processing_status ?? ManualControlProcessingStatus::PENDING,
        ]);

        return $this->state();
    }

    public function setWorkStatus(Request $request): JsonResponse
    {
        $is_working = (bool) $request->boolean('is_working');
        $user = auth()->user();

        if (! $user) {
            return response()->failWithMessage('Пользователь не найден.');
        }

        if (! $is_working && $this->hasActiveOrders((int) $user->id)) {
            return response()->failWithMessage('Нельзя выключить режим работы, пока есть активная заявка.');
        }

        $user->update([
            'manual_control_acq_is_working' => $is_working,
        ]);

        return $this->state();
    }

    public function updateSoundSettings(Request $request): JsonResponse
    {
        $audio_tracks = $this->getAudioTracks();
        $allowed_tracks = array_column($audio_tracks, 'value');

        $validated_data = $request->validate([
            'new_offer' => ['required', 'array'],
            'new_offer.enabled' => ['required', 'boolean'],
            'new_offer.track' => ['nullable', 'string', Rule::in($allowed_tracks)],
            'confirm_code' => ['required', 'array'],
            'confirm_code.enabled' => ['required', 'boolean'],
            'confirm_code.track' => ['nullable', 'string', Rule::in($allowed_tracks)],
        ]);

        $resolved_new_offer_track = $this->resolveSoundTrack(
            $validated_data['new_offer']['track'] ?? null,
            $audio_tracks,
            self::DEFAULT_NEW_OFFER_SOUND_TRACK,
        );
        $resolved_confirm_code_track = $this->resolveSoundTrack(
            $validated_data['confirm_code']['track'] ?? null,
            $audio_tracks,
            self::DEFAULT_CONFIRM_CODE_SOUND_TRACK,
        );

        $request->user()->meta()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'manual_control_acq_new_offer_sound_enabled' => (bool) $validated_data['new_offer']['enabled'],
                'manual_control_acq_new_offer_sound_track' => $resolved_new_offer_track,
                'manual_control_acq_confirm_code_sound_enabled' => (bool) $validated_data['confirm_code']['enabled'],
                'manual_control_acq_confirm_code_sound_track' => $resolved_confirm_code_track,
            ],
        );
        $fresh_meta = $request->user()->meta()->first();

        return response()->success([
            'sound_settings' => $this->resolveSoundSettings($fresh_meta, $audio_tracks),
        ]);
    }

    /**
     * @param Collection<int, Order> $orders
     */
    private function makeQueueItems(Collection $orders): array
    {
        return $orders
            ->map(fn (Order $order) => $this->makeQueueItem($order))
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Order> $orders
     */
    private function makeHistoryQueueItems(Collection $orders): array
    {
        return $orders
            ->map(fn (Order $order) => $this->makeQueueItem($order, true))
            ->values()
            ->all();
    }

    private function makeQueueItem(Order $order, bool $is_history = false): array
    {
        $card_number_raw = (string) ($order->manual_control_card_number ?? '');
        $cardholder_trimmed = trim((string) ($order->manual_control_cardholder_name ?? ''));
        $expiry_date = $this->formatExpiryDate(
            $order->manual_control_expiry_month,
            $order->manual_control_expiry_year,
        );

        $created_at_ts = $order->created_at?->timestamp;
        $expires_at_ts = $order->expires_at?->timestamp;
        $finished_at_ts = $order->finished_at?->timestamp;

        $active_total_seconds = (is_int($created_at_ts) && is_int($expires_at_ts))
            ? max(1, $expires_at_ts - $created_at_ts)
            : (15 * 60);
        $history_total_seconds = (is_int($created_at_ts) && is_int($finished_at_ts))
            ? max(1, $finished_at_ts - $created_at_ts)
            : $active_total_seconds;

        $processing_total_seconds = $is_history ? $history_total_seconds : $active_total_seconds;

        $processing_end_ts = null;
        if ($is_history && is_int($finished_at_ts)) {
            $processing_end_ts = $finished_at_ts;
        } elseif (is_int($expires_at_ts)) {
            $processing_end_ts = $expires_at_ts;
        }

        $processing_elapsed_seconds = 0;
        if (is_int($created_at_ts)) {
            $elapsed_source_ts = $is_history && is_int($finished_at_ts)
                ? $finished_at_ts
                : now()->timestamp;
            $processing_elapsed_seconds = min($processing_total_seconds, max(0, $elapsed_source_ts - $created_at_ts));
        }

        $confirmation_codes = $order->manualControlConfirmationCodes
            ->map(function (OrderManualControlConfirmationCode $confirmation_code): array {
                return [
                    'display' => $confirmation_code->confirmation_code,
                    'copy' => $confirmation_code->confirmation_code,
                    'created_at_ts' => $confirmation_code->created_at?->timestamp,
                ];
            })
            ->values()
            ->all();

        $latest_confirmation_code = $confirmation_codes[0] ?? null;

        return [
            'id' => (string) $order->id,
            'is_history' => $is_history,
            'payin_id' => [
                'display' => $this->shortPayInId($order->uuid),
                'copy' => $order->uuid,
            ],
            'incoming_bank' => [
                'display' => $order->paymentGateway?->name ?? '—',
            ],
            'amount' => [
                'display' => sprintf('%s %s', $order->amount->toBeauty(), strtoupper($order->currency->getCode())),
                'copy' => (string) $order->amount->toInt(),
            ],
            'card_number' => [
                'display' => $this->formatCardNumber($card_number_raw),
                'copy' => $card_number_raw,
            ],
            'expiry_date' => [
                'display' => $expiry_date,
                'copy' => $expiry_date === '—' ? '' : $expiry_date,
            ],
            'cardholder_name' => [
                'display' => $cardholder_trimmed !== '' ? $cardholder_trimmed : '',
                'copy' => $cardholder_trimmed,
            ],
            'processing_elapsed_seconds' => $processing_elapsed_seconds,
            'processing_total_seconds' => $processing_total_seconds,
            'processing_created_at_ts' => $created_at_ts,
            'processing_expires_at_ts' => $expires_at_ts,
            'processing_finished_at_ts' => $finished_at_ts,
            'processing_end_at_ts' => $processing_end_ts,
            'pending_confirmation_title' => $order->manual_control_confirmation_type?->title() ?? '',
            'confirmation_type' => $order->manual_control_confirmation_type?->value,
            'confirmation_code' => $latest_confirmation_code,
            'confirmation_codes' => $confirmation_codes,
            'outcome_status' => $is_history ? $this->resolveHistoryOutcomeStatus($order) : null,
            'reject_reason' => $is_history ? $this->resolveHistoryRejectReason($order) : null,
        ];
    }

    private function makeIncomingOfferPreview(Order $order): array
    {
        $created_at_ts = $order->created_at?->timestamp;
        $expires_at_ts = $order->expires_at?->timestamp;

        $processing_total_seconds = (is_int($created_at_ts) && is_int($expires_at_ts))
            ? max(1, $expires_at_ts - $created_at_ts)
            : (15 * 60);
        $processing_elapsed_seconds = is_int($created_at_ts)
            ? min($processing_total_seconds, max(0, now()->timestamp - $created_at_ts))
            : 0;

        return [
            'id' => (string) $order->id,
            'payin_id' => [
                'display' => $this->shortPayInId($order->uuid),
                'copy' => $order->uuid,
            ],
            'incoming_bank' => [
                'display' => $order->paymentGateway?->name ?? '—',
            ],
            'amount' => [
                'display' => sprintf('%s %s', $order->amount->toBeauty(), strtoupper($order->currency->getCode())),
                'copy' => (string) $order->amount->toInt(),
            ],
            'processing_elapsed_seconds' => $processing_elapsed_seconds,
            'processing_total_seconds' => $processing_total_seconds,
            'processing_created_at_ts' => $created_at_ts,
            'processing_expires_at_ts' => $expires_at_ts,
        ];
    }

    private function canTakeOrder(Order $order): bool
    {
        return $order->manual_control_acquiring
            && $order->status->equals(OrderStatus::PENDING)
            && $this->isPendingProcessingStatus($order)
            && $order->manual_control_taken_by_user_id === null;
    }

    private function canRejectOrder(Order $order): bool
    {
        if (! $order->manual_control_acquiring || $order->status->notEquals(OrderStatus::PENDING)) {
            return false;
        }

        if (! $this->isPendingProcessingStatus($order)) {
            return false;
        }

        return $order->manual_control_taken_by_user_id === null
            || $order->manual_control_taken_by_user_id === auth()->id();
    }

    private function canSetConfirmationType(Order $order): bool
    {
        return $order->manual_control_acquiring
            && $order->status->equals(OrderStatus::PENDING)
            && $this->isPendingProcessingStatus($order)
            && $order->manual_control_taken_by_user_id === auth()->id();
    }

    private function canConfirmOrder(Order $order): bool
    {
        return $order->manual_control_acquiring
            && $order->status->equals(OrderStatus::PENDING)
            && $this->isPendingProcessingStatus($order)
            && $order->manual_control_taken_by_user_id === auth()->id()
            && $order->manual_control_confirmation_type !== null;
    }

    private function shortPayInId(string $uuid): string
    {
        return (string) Str::of($uuid)
            ->replace('-', '')
            ->substr(0, 8)
            ->upper();
    }

    private function formatCardNumber(string $card_number): string
    {
        $digits = preg_replace('/\D+/', '', $card_number);

        if (! $digits) {
            return '—';
        }

        return trim(chunk_split($digits, 4, ' '));
    }

    private function formatExpiryDate(?int $month, ?int $year): string
    {
        if (! $month || ! $year) {
            return '—';
        }

        return sprintf('%02d/%02d', $month, $year % 100);
    }

    private function makeTakeLockKey(int $order_id): string
    {
        return "manual-control-acq:take:{$order_id}";
    }

    private function isCurrentUserWorking(): bool
    {
        return (bool) auth()->user()?->manual_control_acq_is_working;
    }

    private function hasActiveOrders(int $user_id): bool
    {
        return Order::query()
            ->where('manual_control_acquiring', true)
            ->where('status', OrderStatus::PENDING)
            ->where(function ($query) {
                $query
                    ->where('manual_control_processing_status', ManualControlProcessingStatus::PENDING)
                    ->orWhereNull('manual_control_processing_status');
            })
            ->where('manual_control_taken_by_user_id', $user_id)
            ->exists();
    }

    private function isPendingProcessingStatus(Order $order): bool
    {
        return $order->manual_control_processing_status === null
            || $order->manual_control_processing_status === ManualControlProcessingStatus::PENDING;
    }

    private function resolveHistoryOutcomeStatus(Order $order): ?string
    {
        if ($order->status->equals(OrderStatus::SUCCESS)) {
            return 'accepted';
        }

        if ($order->manual_control_processing_status === ManualControlProcessingStatus::CONFIRMED) {
            return 'accepted';
        }

        if ($order->status->equals(OrderStatus::FAIL)) {
            return 'rejected';
        }

        if ($order->manual_control_processing_status === ManualControlProcessingStatus::REJECTED) {
            return 'rejected';
        }

        return null;
    }

    private function resolveHistoryRejectReason(Order $order): ?string
    {
        if (! $order->status->equals(OrderStatus::FAIL)) {
            return null;
        }

        if ($order->manual_control_reject_reason) {
            return $order->manual_control_reject_reason;
        }

        if ($order->sub_status->equals(OrderSubStatus::CANCELED)) {
            return 'Отклонено оператором.';
        }

        return 'Заявка отклонена.';
    }

    private function getAudioTracks(): array
    {
        $audio_directory = public_path('audio');

        if (! File::isDirectory($audio_directory)) {
            return [];
        }

        $priority_sort_map = array_flip(self::PRIORITY_SOUND_TRACKS);

        $all_tracks = collect(File::files($audio_directory))
            ->filter(function ($file) {
                return $file->getExtension() === 'mp3';
            });

        $named_tracks = $all_tracks
            ->reject(function ($file) {
                return preg_match('/^\d+\.mp3$/i', $file->getFilename()) === 1;
            })
            ->sort(function ($left_file, $right_file) use ($priority_sort_map) {
                $left_name = $left_file->getFilename();
                $right_name = $right_file->getFilename();

                $left_priority = $priority_sort_map[$left_name] ?? PHP_INT_MAX;
                $right_priority = $priority_sort_map[$right_name] ?? PHP_INT_MAX;

                if ($left_priority !== $right_priority) {
                    return $left_priority <=> $right_priority;
                }

                return strcmp($left_name, $right_name);
            })
            ->values();

        $numeric_tracks = $all_tracks
            ->filter(function ($file) {
                return preg_match('/^\d+\.mp3$/i', $file->getFilename()) === 1;
            })
            ->sort(function ($left_file, $right_file) {
                $left_numeric_value = (int) preg_replace('/\.mp3$/i', '', $left_file->getFilename());
                $right_numeric_value = (int) preg_replace('/\.mp3$/i', '', $right_file->getFilename());

                return $left_numeric_value <=> $right_numeric_value;
            })
            ->values();

        return $named_tracks
            ->concat($numeric_tracks)
            ->map(function ($file) {
                $name = $file->getFilename();

                return [
                    'name' => $name,
                    'value' => $name,
                    'url' => '/audio/' . $name,
                ];
            })
            ->toArray();
    }

    private function resolveSoundSettings(?UserMeta $user_meta, array $audio_tracks): array
    {
        $new_offer_track = $this->resolveSoundTrack(
            $user_meta?->manual_control_acq_new_offer_sound_track,
            $audio_tracks,
            self::DEFAULT_NEW_OFFER_SOUND_TRACK,
        );
        $confirm_code_track = $this->resolveSoundTrack(
            $user_meta?->manual_control_acq_confirm_code_sound_track,
            $audio_tracks,
            self::DEFAULT_CONFIRM_CODE_SOUND_TRACK,
        );

        return [
            'new_offer' => [
                'enabled' => $user_meta?->manual_control_acq_new_offer_sound_enabled ?? true,
                'track' => $new_offer_track,
            ],
            'confirm_code' => [
                'enabled' => $user_meta?->manual_control_acq_confirm_code_sound_enabled ?? true,
                'track' => $confirm_code_track,
            ],
        ];
    }

    private function resolveSoundTrack(?string $track, array $audio_tracks, string $default_track): ?string
    {
        if (empty($audio_tracks)) {
            return null;
        }

        $allowed_tracks = array_column($audio_tracks, 'value');

        if ($track && in_array($track, $allowed_tracks, true)) {
            return $track;
        }

        if (in_array($default_track, $allowed_tracks, true)) {
            return $default_track;
        }

        return $audio_tracks[0]['value'];
    }
}
