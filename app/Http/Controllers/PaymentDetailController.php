<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\PaymentDetail\StoreRequest;
use App\Http\Requests\PaymentDetail\BulkUpdateRequest;
use App\Http\Requests\PaymentDetail\UpdateRequest;
use App\Http\Resources\PaymentDetailResource;
use App\Http\Resources\PaymentDetailTagResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Http\Resources\UserDeviceResource;
use App\Models\PaymentDetail;
use App\Models\PaymentDetailTag;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Money\Money;
use App\Services\Money\Currency;
use App\Utils\Transaction;
use App\DTO\PaymentDetail\PaymentDetailCreateDTO;
use App\DTO\PaymentDetail\PaymentDetailUpdateDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PaymentDetailController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $fromArchive = request()->tab === 'archived';

        $paymentDetails = queries()->paymentDetail()->paginateForUser(auth()->user(), $filters, $fromArchive);

        $paymentDetailTags = null;
        $canSeeTags = isRouteFor('Trader');
        if ($canSeeTags) {
            $paymentDetails->getCollection()->load('tags');
            $paymentDetailTags = PaymentDetailTagResource::collection(
                PaymentDetailTag::query()
                    ->where('user_id', auth()->id())
                    ->orderBy('name')
                    ->get()
            )->resolve();
        }

        $paymentDetails = PaymentDetailResource::collection($paymentDetails);

        return Inertia::render('PaymentDetail/Index', compact('paymentDetails', 'filters', 'filtersVariants', 'paymentDetailTags'));
    }

    public function create()
    {
        // legacy page endpoint is no longer used (migrated to modal + axios)
        abort(404);
    }

    public function createData(Request $request)
    {
        $paymentGateways = PaymentGatewayResource::collection(queries()->paymentGateway()->getAllActive())->resolve();

        $userId = auth()->id();
        $requestedUserId = (int) $request->input('user_id', 0);
        if ($requestedUserId > 0) {
            if ($requestedUserId !== auth()->id() && ! auth()->user()?->hasRole('Super Admin')) {
                abort(403);
            }
            $userId = $requestedUserId;
        }

        $user = User::findOrFail($userId);
        $canWorkWithoutDevice = (bool) $user->can_work_without_device;

        $devices = $canWorkWithoutDevice
            ? []
            : UserDeviceResource::collection(
                UserDevice::where('user_id', $userId)->get()
            )->resolve();

        return response()->json([
            'success' => true,
            'data' => [
                'paymentGateways' => $paymentGateways,
                'devices' => $devices,
                'canWorkWithoutDevice' => $canWorkWithoutDevice,
            ],
        ]);
    }

    public function store(StoreRequest $request)
    {
        $user = auth()->user();
        $deviceId = $request->user_device_id;
        $device = null;

        if ($deviceId) {
            $device = UserDevice::where('id', $deviceId)
                ->where('user_id', $user->id)
                ->first();
            if (! $device) {
                return $request->expectsJson()
                    ? response()->json([
                        'success' => false,
                        'errors' => [
                            'user_device_id' => ['Устройство не найдено или не принадлежит пользователю'],
                        ],
                    ], 422)
                    : redirect()->back()->withErrors([
                        'user_device_id' => 'Устройство не найдено или не принадлежит пользователю',
                    ]);
            }
        }

        if (! $deviceId && ! $user->can_work_without_device) {
            return $request->expectsJson()
                ? response()->json([
                    'success' => false,
                    'errors' => [
                        'user_device_id' => ['Необходимо выбрать устройство'],
                    ],
                ], 422)
                : redirect()->back()->withErrors([
                    'user_device_id' => 'Необходимо выбрать устройство',
                ]);
        }

        $dto = PaymentDetailCreateDTO::makeFromRequest($request->validated() + [
            'user_id' => auth()->id(),
        ]);
        services()->paymentDetail()->create($dto);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('payment-details.index');
    }

    public function show(PaymentDetail $paymentDetail)
    {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        $paymentDetail->load(['user', 'userDevice', 'paymentGateways']);
        $paymentDetail->loadCount(['orders as pending_orders_count' => function ($query) {
            $query->where('status', OrderStatus::PENDING);
        }]);

        $paymentDetail->setAttribute('payment_gateway_ids', $paymentDetail->paymentGateways()->pluck('payment_gateways.id')->toArray());

        $paymentDetail = PaymentDetailResource::make($paymentDetail)->resolve();

        return response()->json([
            'success' => true,
            'data' => $paymentDetail,
        ]);
    }

    public function update(UpdateRequest $request, PaymentDetail $paymentDetail)
    {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        $owner = $paymentDetail->user;
        $deviceId = $request->user_device_id;
        if ($deviceId) {
            $device = UserDevice::where('id', $deviceId)
                ->where('user_id', $owner->id)
                ->first();

            if (! $device) {
                return $request->expectsJson()
                    ? response()->json([
                        'success' => false,
                        'errors' => [
                            'user_device_id' => ['Устройство не найдено или не принадлежит пользователю'],
                        ],
                    ], 422)
                    : redirect()->back()->withErrors([
                        'user_device_id' => 'Устройство не найдено или не принадлежит пользователю',
                    ]);
            }
        }

        if (! $deviceId && ! $owner->can_work_without_device) {
            return $request->expectsJson()
                ? response()->json([
                    'success' => false,
                    'errors' => [
                        'user_device_id' => ['Необходимо выбрать устройство'],
                    ],
                ], 422)
                : redirect()->back()->withErrors([
                    'user_device_id' => 'Необходимо выбрать устройство',
                ]);
        }

        // Получаем текущие ID платежных методов
        $currentPaymentGatewayIds = $paymentDetail->paymentGateways()->pluck('payment_gateways.id')->toArray();

        // Проверяем, что все текущие ID присутствуют в новом списке
        $missingIds = array_diff($currentPaymentGatewayIds, $request->payment_gateway_ids);
        if (!empty($missingIds)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'payment_gateway_ids' => ['Нельзя удалить уже выбранные платежные методы']
                    ]
                ], 422);
            }
            return redirect()->back()->withErrors([
                'payment_gateway_ids' => 'Нельзя удалить уже выбранные платежные методы'
            ]);
        }

        $dto = PaymentDetailUpdateDTO::makeFromRequest($request->validated());
        services()->paymentDetail()->update($dto, $paymentDetail);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('payment-details.index');
    }

    public function bulkUpdate(BulkUpdateRequest $request)
    {
        $user = $request->user();
        $fields = $request->input('fields', []);
        $scope = $request->input('scope');

        $query = PaymentDetail::query()
            ->where('user_id', $user->id)
            ->with('paymentGateways');

        if ($scope === 'tag') {
            $tagId = (int) $request->input('tag_id');
            $query->whereHas('tags', function ($tagQuery) use ($tagId) {
                $tagQuery->where('payment_detail_tags.id', $tagId);
            });
        }

        if ($scope === 'without_tags') {
            $query->whereDoesntHave('tags');
        }

        $paymentDetails = $query->get();

        $updatedCount = 0;
        foreach ($paymentDetails as $detail) {
            Gate::authorize('access-to-payment-detail', $detail);

            $payload = $this->buildBulkUpdatePayload($detail, $fields, $request);
            $dto = PaymentDetailUpdateDTO::makeFromRequest($payload);

            services()->paymentDetail()->update($dto, $detail);
            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
        ]);
    }

    public function toggleActive(PaymentDetail $paymentDetail)
    {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        services()->paymentDetail()->toggleActive($paymentDetail);
    }

    protected function buildBulkUpdatePayload(PaymentDetail $detail, array $fields, Request $request): array
    {
        $payload = [
            'name' => $detail->name,
            'initials' => $detail->initials,
            'is_active' => (bool) $detail->is_active,
            'daily_limit' => (int) $detail->daily_limit->toPrecision(),
            'daily_successful_orders_limit' => $detail->daily_successful_orders_limit,
            'max_pending_orders_quantity' => $detail->max_pending_orders_quantity,
            'min_order_amount' => $detail->min_order_amount ? (int) $detail->min_order_amount->toPrecision() : null,
            'max_order_amount' => $detail->max_order_amount ? (int) $detail->max_order_amount->toPrecision() : null,
            'order_interval_minutes' => $detail->order_interval_minutes,
            'user_device_id' => $detail->user_device_id,
            'payment_gateway_ids' => $detail->paymentGateways->pluck('id')->all(),
        ];

        if (in_array('is_active', $fields, true)) {
            $payload['is_active'] = (bool) $request->input('is_active');
        }
        if (in_array('daily_limit', $fields, true)) {
            $payload['daily_limit'] = $request->input('daily_limit');
        }
        if (in_array('daily_successful_orders_limit', $fields, true)) {
            $payload['daily_successful_orders_limit'] = $request->input('daily_successful_orders_limit');
        }
        if (in_array('max_pending_orders_quantity', $fields, true)) {
            $payload['max_pending_orders_quantity'] = $request->input('max_pending_orders_quantity');
        }
        if (in_array('min_order_amount', $fields, true)) {
            $payload['min_order_amount'] = $request->input('min_order_amount');
        }
        if (in_array('max_order_amount', $fields, true)) {
            $payload['max_order_amount'] = $request->input('max_order_amount');
        }
        if (in_array('order_interval_minutes', $fields, true)) {
            $payload['order_interval_minutes'] = $request->input('order_interval_minutes');
        }

        return $payload;
    }
}
