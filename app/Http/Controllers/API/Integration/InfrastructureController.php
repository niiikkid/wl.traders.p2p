<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Integration;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\Payout\Payout;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfrastructureController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with('roles')->whereNull('archived_at');

        $this->applyIdFilter($query, 'id', $request->string('ids')->toString());
        $this->applyExactFilter($query, 'email', $request->string('email')->toString());
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, static fn (User $user): array => [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $user->roles->pluck('name')->values(),
            'is_online' => (bool) $user->is_online,
            'can_set_order_amount_limits' => (bool) $user->can_set_order_amount_limits,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ]);
    }

    public function user(User $user): JsonResponse
    {
        $user->loadMissing(['roles', 'wallet']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'roles' => $user->roles->pluck('name')->values(),
                'is_online' => (bool) $user->is_online,
                'can_set_order_amount_limits' => (bool) $user->can_set_order_amount_limits,
                'wallet' => $user->wallet ? [
                    'id' => $user->wallet->id,
                ] : null,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function paymentDetails(Request $request): JsonResponse
    {
        $query = PaymentDetail::query()->with(['user', 'paymentGateways']);

        $this->applyIdFilter($query, 'id', $request->string('ids')->toString());
        $this->applyIdFilter($query, 'user_id', $request->string('user_ids')->toString());
        $this->applyExactFilter($query, 'is_active', $request->query('is_active'));
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, static fn (PaymentDetail $detail): array => [
            'id' => $detail->id,
            'uuid' => $detail->uuid,
            'name' => $detail->name,
            'detail' => $detail->detail,
            'detail_type' => $detail->detail_type?->value,
            'is_active' => (bool) $detail->is_active,
            'user' => [
                'id' => $detail->user?->id,
                'email' => $detail->user?->email,
            ],
            'payment_methods' => $detail->paymentGateways->map(static fn ($gateway): array => [
                'id' => $gateway->id,
                'code' => $gateway->code,
                'name' => $gateway->name,
            ])->values(),
            'created_at' => $detail->created_at?->toIso8601String(),
            'updated_at' => $detail->updated_at?->toIso8601String(),
        ]);
    }

    public function paymentDetail(PaymentDetail $paymentDetail): JsonResponse
    {
        $paymentDetail->loadMissing(['user', 'paymentGateways']);

        return response()->json([
            'data' => [
                'id' => $paymentDetail->id,
                'uuid' => $paymentDetail->uuid,
                'name' => $paymentDetail->name,
                'detail' => $paymentDetail->detail,
                'detail_type' => $paymentDetail->detail_type?->value,
                'is_active' => (bool) $paymentDetail->is_active,
                'user' => [
                    'id' => $paymentDetail->user?->id,
                    'email' => $paymentDetail->user?->email,
                ],
                'payment_methods' => $paymentDetail->paymentGateways->map(static fn ($gateway): array => [
                    'id' => $gateway->id,
                    'code' => $gateway->code,
                    'name' => $gateway->name,
                ])->values(),
                'created_at' => $paymentDetail->created_at?->toIso8601String(),
                'updated_at' => $paymentDetail->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $query = Order::query()->with(['trader', 'paymentGateway', 'paymentDetail.user', 'merchant']);

        $this->applyIdFilter($query, 'trader_id', $request->string('user_ids')->toString());
        $this->applyExactFilter($query, 'status', $request->string('status')->toString());
        $this->applyExactFilter($query, 'payment_detail_id', $request->query('payment_detail_id'));
        $this->applyExactFilter($query, 'payment_gateway_id', $request->query('payment_gateway_id'));
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, fn (Order $order): array => $this->mapOrder($order));
    }

    public function order(Order $order): JsonResponse
    {
        $order->loadMissing(['trader', 'paymentGateway', 'paymentDetail.user', 'merchant']);

        return response()->json([
            'data' => $this->mapOrder($order),
        ]);
    }

    public function disputes(Request $request): JsonResponse
    {
        $query = Dispute::query()->with(['trader', 'order']);

        $this->applyIdFilter($query, 'order_id', $request->string('order_ids')->toString());
        $this->applyExactFilter($query, 'status', $request->string('status')->toString());
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, static fn (Dispute $dispute): array => [
            'id' => $dispute->id,
            'uuid' => $dispute->uuid,
            'status' => $dispute->status?->value,
            'reason' => $dispute->reason,
            'trader' => [
                'id' => $dispute->trader?->id,
                'email' => $dispute->trader?->email,
            ],
            'order' => [
                'id' => $dispute->order?->id,
                'uuid' => $dispute->order?->uuid,
            ],
            'created_at' => $dispute->created_at?->toIso8601String(),
            'updated_at' => $dispute->updated_at?->toIso8601String(),
        ]);
    }

    public function dispute(Dispute $dispute): JsonResponse
    {
        $dispute->loadMissing(['trader', 'order']);

        return response()->json([
            'data' => [
                'id' => $dispute->id,
                'uuid' => $dispute->uuid,
                'status' => $dispute->status?->value,
                'reason' => $dispute->reason,
                'receipt' => $dispute->receipt,
                'trader' => [
                    'id' => $dispute->trader?->id,
                    'email' => $dispute->trader?->email,
                ],
                'order' => [
                    'id' => $dispute->order?->id,
                    'uuid' => $dispute->order?->uuid,
                ],
                'created_at' => $dispute->created_at?->toIso8601String(),
                'updated_at' => $dispute->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['wallet.user']);

        $this->applyIdFilter($query, 'wallet_id', $request->string('wallet_ids')->toString());
        $invoiceType = $request->string('type')->toString();
        if ($invoiceType === '' && is_string($request->route('type'))) {
            $invoiceType = (string) $request->route('type');
        }

        $this->applyExactFilter($query, 'type', $invoiceType);
        $this->applyExactFilter($query, 'status', $request->string('status')->toString());
        $this->applyExactFilter($query, 'balance_type', $request->string('balance_type')->toString());

        $this->applyIdFilter($query, 'wallet_id', $this->walletIdsByUsers($request->string('user_ids')->toString()));
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, static fn (Invoice $invoice): array => [
            'id' => $invoice->id,
            'external_id' => $invoice->external_id,
            'amount' => $invoice->amount->toBeauty(),
            'currency' => $invoice->currency->getCode(),
            'address' => $invoice->address,
            'network' => $invoice->network->value,
            'tx_hash' => $invoice->tx_hash,
            'type' => $invoice->type->value,
            'balance_type' => $invoice->balance_type->value,
            'status' => $invoice->status->value,
            'wallet' => [
                'id' => $invoice->wallet?->id,
                'user_id' => $invoice->wallet?->user?->id,
                'user_email' => $invoice->wallet?->user?->email,
            ],
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ]);
    }

    public function invoice(Invoice $invoice): JsonResponse
    {
        $invoice->loadMissing(['wallet.user']);

        return response()->json([
            'data' => [
                'id' => $invoice->id,
                'external_id' => $invoice->external_id,
                'amount' => $invoice->amount->toBeauty(),
                'currency' => $invoice->currency->getCode(),
                'address' => $invoice->address,
                'network' => $invoice->network->value,
                'tx_hash' => $invoice->tx_hash,
                'type' => $invoice->type->value,
                'balance_type' => $invoice->balance_type->value,
                'status' => $invoice->status->value,
                'wallet' => [
                    'id' => $invoice->wallet?->id,
                    'user_id' => $invoice->wallet?->user?->id,
                    'user_email' => $invoice->wallet?->user?->email,
                ],
                'created_at' => $invoice->created_at?->toIso8601String(),
                'updated_at' => $invoice->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $query = Payout::query()->with(['trader', 'paymentGateway', 'merchant']);

        $this->applyIdFilter($query, 'trader_id', $request->string('user_ids')->toString());
        $this->applyExactFilter($query, 'status', $request->string('status')->toString());
        $this->applyExactFilter($query, 'payment_gateway_id', $request->query('payment_gateway_id'));
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, static fn (Payout $payout): array => [
            'id' => $payout->id,
            'uuid' => $payout->uuid,
            'external_id' => $payout->external_id,
            'status' => $payout->status?->value,
            'amount_fiat' => $payout->amount_fiat->toBeauty(),
            'payout_method_type' => $payout->payout_method_type->value,
            'requisites' => $payout->requisites,
            'merchant' => [
                'id' => $payout->merchant?->id,
                'uuid' => $payout->merchant?->uuid,
            ],
            'trader' => [
                'id' => $payout->trader?->id,
                'email' => $payout->trader?->email,
            ],
            'payment_method' => [
                'id' => $payout->paymentGateway?->id,
                'code' => $payout->paymentGateway?->code,
                'name' => $payout->paymentGateway?->name,
            ],
            'created_at' => $payout->created_at?->toIso8601String(),
            'updated_at' => $payout->updated_at?->toIso8601String(),
        ]);
    }

    public function payout(Payout $payout): JsonResponse
    {
        $payout->loadMissing(['trader', 'paymentGateway', 'merchant']);

        return response()->json([
            'data' => [
                'id' => $payout->id,
                'uuid' => $payout->uuid,
                'external_id' => $payout->external_id,
                'status' => $payout->status?->value,
                'amount_fiat' => $payout->amount_fiat->toBeauty(),
                'payout_method_type' => $payout->payout_method_type->value,
                'requisites' => $payout->requisites,
                'merchant' => [
                    'id' => $payout->merchant?->id,
                    'uuid' => $payout->merchant?->uuid,
                ],
                'trader' => [
                    'id' => $payout->trader?->id,
                    'email' => $payout->trader?->email,
                ],
                'payment_method' => [
                    'id' => $payout->paymentGateway?->id,
                    'code' => $payout->paymentGateway?->code,
                    'name' => $payout->paymentGateway?->name,
                ],
                'created_at' => $payout->created_at?->toIso8601String(),
                'updated_at' => $payout->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function wallet(Wallet $wallet): JsonResponse
    {
        $wallet->loadMissing('user');

        return response()->json([
            'data' => [
                'id' => $wallet->id,
                'user' => [
                    'id' => $wallet->user?->id,
                    'email' => $wallet->user?->email,
                ],
                'balances' => [
                    'merchant_balance' => $wallet->merchant_balance->toBeauty(),
                    'trust_balance' => $wallet->trust_balance->toBeauty(),
                    'reserve_balance' => $wallet->reserve_balance->toBeauty(),
                    'commission_balance' => $wallet->commission_balance->toBeauty(),
                    'teamleader_balance' => $wallet->teamleader_balance->toBeauty(),
                    'agent_balance' => $wallet->agent_balance?->toBeauty(),
                ],
                'created_at' => $wallet->created_at?->toIso8601String(),
                'updated_at' => $wallet->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function walletTransactions(Request $request, Wallet $wallet): JsonResponse
    {
        $query = $wallet->transactions()->getQuery();
        $query->with('wallet.user');

        $this->applyExactFilter($query, 'type', $request->string('type')->toString());
        $this->applyExactFilter($query, 'direction', $request->string('direction')->toString());
        $this->applyExactFilter($query, 'balance_type', $request->string('balance_type')->toString());
        $this->applyDateRangeFilter($query, 'created_at', $request);

        return $this->respondPaginated($request, $query, static fn (Transaction $transaction): array => [
            'id' => $transaction->id,
            'amount' => $transaction->amount->toBeauty(),
            'direction' => $transaction->direction->value,
            'type' => $transaction->type->value,
            'balance_type' => $transaction->balance_type->value,
            'wallet' => [
                'id' => $transaction->wallet?->id,
                'user_id' => $transaction->wallet?->user?->id,
                'user_email' => $transaction->wallet?->user?->email,
            ],
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ]);
    }

    public function walletTransaction(Wallet $wallet, Transaction $transaction): JsonResponse
    {
        if ($transaction->wallet_id !== $wallet->id) {
            abort(404);
        }

        $transaction->loadMissing('wallet.user');

        return response()->json([
            'data' => [
                'id' => $transaction->id,
                'amount' => $transaction->amount->toBeauty(),
                'direction' => $transaction->direction->value,
                'type' => $transaction->type->value,
                'balance_type' => $transaction->balance_type->value,
                'wallet' => [
                    'id' => $transaction->wallet?->id,
                    'user_id' => $transaction->wallet?->user?->id,
                    'user_email' => $transaction->wallet?->user?->email,
                ],
                'created_at' => $transaction->created_at?->toIso8601String(),
                'updated_at' => $transaction->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function userOffers(Request $request, User $user): JsonResponse
    {
        $query = $user->paymentDetails()->getQuery();
        $query->with('paymentGateways');
        $this->applyExactFilter($query, 'is_active', $request->query('is_active'));

        return $this->respondPaginated($request, $query, static fn (PaymentDetail $detail): array => [
            'payment_detail_id' => $detail->id,
            'payment_detail_name' => $detail->name,
            'payment_methods' => $detail->paymentGateways->map(static fn ($gateway): array => [
                'id' => $gateway->id,
                'code' => $gateway->code,
                'name' => $gateway->name,
            ])->values(),
            'created_at' => $detail->created_at?->toIso8601String(),
            'updated_at' => $detail->updated_at?->toIso8601String(),
        ]);
    }

    private function mapOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'external_id' => $order->external_id,
            'status' => $order->status?->value,
            'sub_status' => $order->sub_status?->value,
            'amount' => $order->amount->toBeauty(),
            'currency' => $order->currency->getCode(),
            'merchant' => [
                'id' => $order->merchant?->id,
                'uuid' => $order->merchant?->uuid,
            ],
            'user' => [
                'id' => $order->trader?->id,
                'email' => $order->trader?->email,
            ],
            'payment_detail' => [
                'id' => $order->paymentDetail?->id,
                'name' => $order->paymentDetail?->name,
                'user_id' => $order->paymentDetail?->user?->id,
            ],
            'payment_method' => [
                'id' => $order->paymentGateway?->id,
                'code' => $order->paymentGateway?->code,
                'name' => $order->paymentGateway?->name,
            ],
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    private function respondPaginated(Request $request, Builder $query, callable $mapper): JsonResponse
    {
        $paginator = $query
            ->latest('id')
            ->paginate($this->resolvePerPage($request))
            ->appends($request->query());

        return response()->json([
            'data' => collect($paginator->items())->map($mapper)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 10);

        if ($perPage <= 0) {
            return 10;
        }

        return min($perPage, 100);
    }

    private function applyIdFilter(Builder $query, string $column, string $rawIds): void
    {
        $ids = collect(explode(',', $rawIds))
            ->map(static fn (string $value): int => (int) trim($value))
            ->filter(static fn (int $value): bool => $value > 0)
            ->values();

        if ($ids->isNotEmpty()) {
            $query->whereIn($column, $ids);
        }
    }

    private function applyExactFilter(Builder $query, string $column, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->where($column, $value);
    }

    private function applyDateRangeFilter(Builder $query, string $column, Request $request): void
    {
        $from = $request->string('date_from')->toString();
        $to = $request->string('date_to')->toString();

        if ($from !== '') {
            $query->where($column, '>=', $from);
        }

        if ($to !== '') {
            $query->where($column, '<=', $to);
        }
    }

    private function walletIdsByUsers(string $rawUserIds): string
    {
        $userIds = collect(explode(',', $rawUserIds))
            ->map(static fn (string $value): int => (int) trim($value))
            ->filter(static fn (int $value): bool => $value > 0)
            ->values();

        if ($userIds->isEmpty()) {
            return '';
        }

        return Wallet::query()
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->implode(',');
    }
}
