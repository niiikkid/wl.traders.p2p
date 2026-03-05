<?php

namespace App\Http\Controllers;

use App\Contracts\OrderServiceContract;
use App\DTO\Order\CreateOrderDTO;
use App\Exceptions\OrderException;
use App\Http\Requests\Payment\StoreRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\Merchant;
use App\Services\Money\Currency;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = queries()->order()->paginateForMerchant(auth()->user(), $filters);
        $orders = OrderResource::collection($orders);

        return Inertia::render('Payment/Index', compact('orders', 'filters', 'filtersVariants'));
    }

    public function createData()
    {
        $paymentGateways = PaymentGatewayResource::collection(queries()->paymentGateway()->getAllActive())->resolve();

        $currencies = Currency::getAll()->transform(function ($currency) {
            return [
                'code' => strtoupper($currency->getCode()),
                'name' => strtoupper($currency->getCode()) . ' - ' . $currency->getName(),
            ];
        })->toArray();

        $merchants = Merchant::query()
            ->where('user_id', auth()->user()->id)
            ->whereNotNull('validated_at')
            ->whereNull('banned_at')
            ->where('active', true)
            ->orderByDesc('id')
            ->get()
            ->transform(function (Merchant $merchant) {
                $data['id'] = $merchant->id;
                $data['name'] = $merchant->name;

                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'paymentGateways' => $paymentGateways,
                'currencies' => $currencies,
                'merchants' => $merchants,
            ],
        ]);
    }

    public function store(StoreRequest $request)
    {
        $merchant = Merchant::where('id', $request->merchant_id)->first();

        Gate::authorize('access-to-merchant', $merchant);

        try {
            make(OrderServiceContract::class)->create(
                CreateOrderDTO::makeFromRequest(
                    $request->all() + ['merchant' => $merchant],
                )
            );
        } catch (OrderException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->with('message', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('payments.index');
    }
}
