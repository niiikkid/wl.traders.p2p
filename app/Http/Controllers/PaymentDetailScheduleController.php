<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\PaymentDetailSchedule\PaymentDetailScheduleCopyDTO;
use App\DTO\PaymentDetailSchedule\PaymentDetailScheduleUpsertDTO;
use App\Http\Requests\PaymentDetailSchedule\CopyRequest;
use App\Http\Requests\PaymentDetailSchedule\StoreRequest;
use App\Http\Requests\PaymentDetailSchedule\UpdateRequest;
use App\Http\Resources\PaymentDetailScheduleResource;
use App\Models\PaymentDetailSchedule;
use App\Services\PaymentDetail\PaymentDetailScheduleService;
use Illuminate\Http\JsonResponse;

class PaymentDetailScheduleController extends Controller
{
    public function __construct(
        private PaymentDetailScheduleService $scheduleService,
    ) {}

    public function index(): JsonResponse
    {
        $this->ensureTrader();

        $schedules = PaymentDetailSchedule::query()
            ->where('user_id', auth()->id())
            ->with([
                'intervals' => fn ($query) => $query
                    ->orderBy('day_of_week')
                    ->orderBy('starts_at'),
            ])
            ->withCount('paymentDetails')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'server_timezone' => config('app.timezone'),
                'server_now' => now()->toISOString(),
                'schedules' => PaymentDetailScheduleResource::collection($schedules)->resolve(),
            ],
        ]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $this->ensureTrader();

        $schedule = $this->scheduleService->create(
            (int) auth()->id(),
            PaymentDetailScheduleUpsertDTO::makeFromRequest($request->validated()),
        );

        $schedule->loadCount('paymentDetails');

        return response()->json([
            'success' => true,
            'data' => PaymentDetailScheduleResource::make($schedule)->resolve(),
        ]);
    }

    public function update(UpdateRequest $request, PaymentDetailSchedule $paymentDetailSchedule): JsonResponse
    {
        $this->ensureTrader();
        $this->ensureOwner($paymentDetailSchedule);

        $schedule = $this->scheduleService->update(
            $paymentDetailSchedule,
            PaymentDetailScheduleUpsertDTO::makeFromRequest($request->validated()),
        );

        $schedule->loadCount('paymentDetails');

        return response()->json([
            'success' => true,
            'data' => PaymentDetailScheduleResource::make($schedule)->resolve(),
        ]);
    }

    public function copy(CopyRequest $request, PaymentDetailSchedule $paymentDetailSchedule): JsonResponse
    {
        $this->ensureTrader();
        $this->ensureOwner($paymentDetailSchedule);

        $schedule = $this->scheduleService->copy(
            $paymentDetailSchedule,
            PaymentDetailScheduleCopyDTO::makeFromRequest($request->validated()),
        );

        $schedule->loadCount('paymentDetails');

        return response()->json([
            'success' => true,
            'data' => PaymentDetailScheduleResource::make($schedule)->resolve(),
        ]);
    }

    private function ensureTrader(): void
    {
        if (! isRouteFor('Trader')) {
            abort(403);
        }
    }

    private function ensureOwner(PaymentDetailSchedule $paymentDetailSchedule): void
    {
        if ((int) $paymentDetailSchedule->user_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
