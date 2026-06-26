<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OrderSmsLogException;
use App\Http\Resources\SmsLogResource;
use App\Models\SmsLog;
use App\Services\Sms\SmsLogRejectService;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SmsLogRejectController extends Controller
{
    public function store(SmsLog $smsLog, SmsLogRejectService $smsLogRejectService)
    {
        Gate::authorize('access-to-sms-log', $smsLog);

        if ($smsLog->order_id !== null) {
            return response()->failWithMessage('К сообщению уже привязана сделка.', 422);
        }

        if ($smsLog->rejected_at !== null) {
            return response()->failWithMessage('Сообщение уже отклонено.', 422);
        }

        if (! SmsLog::query()->whereKey($smsLog->id)->unlinked()->linkableToOrder()->exists()) {
            return response()->failWithMessage('Сообщение недоступно для отклонения.', 422);
        }

        try {
            $rejectedSmsLog = $smsLogRejectService->reject($smsLog);
        } catch (OrderSmsLogException $exception) {
            return response()->failWithMessage($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->failWithMessage('Не удалось отклонить сообщение.', 500);
        }

        $rejectedSmsLog->load([
            'device',
            'order.paymentDetail',
            'order.paymentGateway',
            'user',
        ]);

        return response()->success([
            'sms_log' => SmsLogResource::make($rejectedSmsLog)->resolve(),
        ]);
    }
}
