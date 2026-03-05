<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmsLogResource;
use App\Models\SenderStopList;
use App\Models\SmsLog;
use App\Models\SmsStopWord;
use Inertia\Inertia;

class SmsLogController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();

        $query = SmsLog::query()
            ->with('user','device', 'order')
            ->when($filters->search, function ($query) use ($filters) {
                $query->where('message', 'like', '%' . strtolower($filters->search) . '%');
            })
            ->when($filters->onlySuccessParsing, function ($query) {
                $query->whereNotNull('parsing_result');
            });

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

        return Inertia::render('SmsLog/Index', compact(
            'smsLogs',
            'smsLogsTotalCount',
            'senderStopList',
            'smsStopWords',
            'filters'
        ));
    }
}
