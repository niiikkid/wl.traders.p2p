<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ManualControlAcqController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/ManualControlAcq/Show');
    }

    public function state(): JsonResponse
    {
        $active_orders = Order::query()
            ->with('paymentGateway')
            ->where('manual_control_acquiring', true)
            ->where('status', OrderStatus::PENDING)
            ->where('manual_control_taken_by_user_id', auth()->id())
            ->orderByDesc('manual_control_taken_at')
            ->orderBy('id')
            ->get();

        $incoming_order = Order::query()
            ->with('paymentGateway')
            ->where('manual_control_acquiring', true)
            ->where('status', OrderStatus::PENDING)
            ->whereNull('manual_control_taken_by_user_id')
            ->orderBy('created_at')
            ->first();

        return response()->success([
            'incoming_offer' => $incoming_order ? $this->makeIncomingOfferPreview($incoming_order) : null,
            'active_queue_items' => $this->makeQueueItems($active_orders),
            'history_queue_items' => [],
            'history_total_count' => 0,
        ]);
    }

    public function take(Order $order): JsonResponse
    {
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

    private function makeQueueItem(Order $order): array
    {
        $card_number_raw = (string) ($order->manual_control_card_number ?? '');
        $expiry_date = $this->formatExpiryDate(
            $order->manual_control_expiry_month,
            $order->manual_control_expiry_year,
        );

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
            'is_history' => false,
            'payin_id' => [
                'display' => $this->shortPayInId($order->uuid),
                'copy' => $order->uuid,
            ],
            'incoming_bank' => [
                'display' => $order->paymentGateway?->name ?? '—',
            ],
            'amount' => [
                'display' => $order->amount->toBeauty(),
                'copy' => $order->amount->toPrecision(),
            ],
            'card_number' => [
                'display' => $this->formatCardNumber($card_number_raw),
                'copy' => $card_number_raw,
            ],
            'expiry_date' => [
                'display' => $expiry_date,
                'copy' => $expiry_date === '—' ? '' : $expiry_date,
            ],
            'cvv' => [
                'display' => (string) ($order->manual_control_cvc ?? '—'),
                'copy' => (string) ($order->manual_control_cvc ?? ''),
            ],
            'processing_elapsed_seconds' => $processing_elapsed_seconds,
            'processing_total_seconds' => $processing_total_seconds,
            'processing_created_at_ts' => $created_at_ts,
            'processing_expires_at_ts' => $expires_at_ts,
            'pending_confirmation_title' => '',
            'confirm_seconds_remaining' => 0,
            'confirmation_code' => null,
        ];
    }

    private function makeIncomingOfferPreview(Order $order): array
    {
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
                'display' => $order->amount->toBeauty(),
                'copy' => $order->amount->toPrecision(),
            ],
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
}
