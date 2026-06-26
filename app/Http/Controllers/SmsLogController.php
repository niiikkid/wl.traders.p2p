<?php

namespace App\Http\Controllers;

use App\Http\Resources\SmsLogResource;
use App\Models\SmsLog;
use Inertia\Inertia;

class SmsLogController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $smsLogs = SmsLog::query()
            ->whereRelation('user', 'id', auth()->id())
            ->with([
                'device',
                'order.paymentDetail',
                'order.paymentGateway',
            ])
            ->when($filters->search, function ($query) use ($filters) {
                $query->where('message', 'like', '%'.strtolower($filters->search).'%');
            })
            ->whereSmsOperationTypes($filters->smsOperationTypes)
            ->whereOnlyUnlinkedIncoming($filters->onlyUnlinkedIncoming)
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $smsLogs = SmsLogResource::collection($smsLogs);

        return Inertia::render('SmsLog/Index', compact('smsLogs', 'filters', 'filtersVariants'));
    }
}
