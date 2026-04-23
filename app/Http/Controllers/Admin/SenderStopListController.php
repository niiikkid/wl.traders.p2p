<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\SenderStopList;
use App\Models\SmsLog;
use Illuminate\Http\Request;

class SenderStopListController extends Controller
{
    public function store(SmsLog $smsLog)
    {
        SenderStopList::create([
            'sender' => $smsLog->sender,
        ]);

        SmsLog::where('sender', $smsLog->sender)->delete();
    }

    public function destroy(SenderStopList $senderStopList)
    {
        $senderStopList->delete();
    }

    public function attachToPaymentGateway(Request $request, SmsLog $smsLog)
    {
        $validated = $request->validate([
            'payment_gateway_id' => ['required', 'integer', 'exists:payment_gateways,id'],
        ]);

        $paymentGateway = PaymentGateway::query()->findOrFail($validated['payment_gateway_id']);
        $sender = trim((string) $smsLog->sender);

        if ($sender === '') {
            return response()->json([
                'message' => 'Невозможно добавить пустого отправителя.',
            ], 422);
        }

        $smsSenders = collect($paymentGateway->sms_senders ?? [])
            ->map(fn (mixed $value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        if (! $smsSenders->contains($sender)) {
            $smsSenders->push($sender);
        }

        $paymentGateway->update([
            'sms_senders' => $smsSenders->all(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
