<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmsLogResource;
use App\Models\PaymentGateway;
use App\Models\SenderStopList;
use App\Models\SmsLog;
use App\Models\SmsStopWord;
use Inertia\Inertia;

class SmsLogController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $query = SmsLog::query()
            ->with([
                'user',
                'device',
                'order.paymentDetail',
                'order.paymentGateway',
            ])
            ->when($filters->search, function ($query) use ($filters) {
                $query->where('message', 'like', '%'.strtolower($filters->search).'%');
            })
            ->when($filters->onlySuccessParsing, function ($query) {
                $query->whereNotNull('parsing_result');
            })
            ->whereSmsOperationTypes($filters->smsOperationTypes)
            ->whereOnlyUnlinkedIncoming($filters->onlyUnlinkedIncoming);

        $smsLogs = $query->clone()
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $smsLogs = SmsLogResource::collection($smsLogs);

        $smsLogsTotalCount = $query->clone()->count();

        $senderStopList = SenderStopList::all()
            ->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'sender' => $item->sender,
                ];
            });

        $smsStopWords = SmsStopWord::all()
            ->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'word' => $item->word,
                ];
            });

        $paymentGateways = PaymentGateway::query()
            ->orderBy('name')
            ->get(['id', 'name', 'logo'])
            ->transform(function (PaymentGateway $paymentGateway) {
                return [
                    'id' => $paymentGateway->id,
                    'name' => $paymentGateway->name,
                    'logo_path' => $paymentGateway->logo ? asset('storage/logos/'.$paymentGateway->logo) : null,
                ];
            })
            ->values();

        $recentPaymentGateways = PaymentGateway::query()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'name', 'logo'])
            ->transform(function (PaymentGateway $paymentGateway) {
                return [
                    'id' => $paymentGateway->id,
                    'name' => $paymentGateway->name,
                    'logo_path' => $paymentGateway->logo ? asset('storage/logos/'.$paymentGateway->logo) : null,
                ];
            })
            ->values();

        return Inertia::render('SmsLog/Index', compact(
            'smsLogs',
            'smsLogsTotalCount',
            'senderStopList',
            'smsStopWords',
            'paymentGateways',
            'recentPaymentGateways',
            'filters',
            'filtersVariants'
        ));
    }
}
