<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ManualControlConfirmationType;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderManualControlConfirmationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualControlAcqController extends Controller
{
    private const HISTORY_DISPLAY_LIMIT = 5;

    public function show(): Response
    {
        return Inertia::render('Admin/ManualControlAcq/Show');
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
            ->where('manual_control_taken_by_user_id', auth()->id())
            ->orderByDesc('manual_control_taken_at')
            ->orderBy('id')
            ->get();

        $incoming_order_query = Order::query()
            ->with('paymentGateway')
            ->where('manual_control_acquiring', true)
            ->where('status', OrderStatus::PENDING)
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
            ->where('status', '!=', OrderStatus::PENDING)
            ->orderByDesc('finished_at')
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
                ->whereNull('manual_control_taken_by_user_id')
                ->update([
                    'manual_control_taken_by_user_id' => auth()->id(),
                    'manual_control_taken_at' => now(),
                ]);

            if ($updated_rows === 0) {
                return response()->failWithMessage('Заявку уже взял в обработку другой пользователь.');
            }
        } finally {
            $lock->release();
        }

        return $this->state();
    }

    public function reject(Order $order): JsonResponse
    {
        if (! $this->isCurrentUserWorking()) {
            return response()->failWithMessage('Режим работы выключен. Включите его, чтобы отклонять заявки.');
        }

        $latest_order = Order::query()->whereKey($order->id)->firstOrFail();

        if (! $this->canRejectOrder($latest_order)) {
            return response()->failWithMessage('Заявка уже недоступна для отклонения.');
        }

        try {
            services()->order()->finishOrderAsFailed($latest_order->id, OrderSubStatus::CANCELED);
        } catch (OrderException $e) {
            return response()->failWithMessage($e->getMessage());
        }

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
            'processing_elapsed_seconds' => $processing_elapsed_seconds,
            'processing_total_seconds' => $processing_total_seconds,
            'processing_created_at_ts' => $created_at_ts,
            'processing_expires_at_ts' => $expires_at_ts,
            'processing_finished_at_ts' => $finished_at_ts,
            'processing_end_at_ts' => $processing_end_ts,
            'pending_confirmation_title' => $order->manual_control_confirmation_type?->title() ?? '',
            'confirmation_type' => $order->manual_control_confirmation_type?->value,
            'confirm_seconds_remaining' => 0,
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
            && $order->manual_control_taken_by_user_id === null;
    }

    private function canRejectOrder(Order $order): bool
    {
        if (! $order->manual_control_acquiring || $order->status->notEquals(OrderStatus::PENDING)) {
            return false;
        }

        return $order->manual_control_taken_by_user_id === null
            || $order->manual_control_taken_by_user_id === auth()->id();
    }

    private function canSetConfirmationType(Order $order): bool
    {
        return $order->manual_control_acquiring
            && $order->status->equals(OrderStatus::PENDING)
            && $order->manual_control_taken_by_user_id === auth()->id();
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
            ->where('manual_control_taken_by_user_id', $user_id)
            ->exists();
    }

    private function resolveHistoryOutcomeStatus(Order $order): ?string
    {
        if ($order->status->equals(OrderStatus::SUCCESS)) {
            return 'accepted';
        }

        if ($order->status->equals(OrderStatus::FAIL)) {
            return 'rejected';
        }

        return null;
    }

    private function resolveHistoryRejectReason(Order $order): ?string
    {
        if (! $order->status->equals(OrderStatus::FAIL)) {
            return null;
        }

        if ($order->sub_status->equals(OrderSubStatus::CANCELED)) {
            return 'Отклонено оператором.';
        }

        return 'Заявка отклонена.';
    }
}
