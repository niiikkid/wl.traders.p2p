<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmsLogResource;
use App\Models\SmsLog;
use Inertia\Inertia;

class SmsLogController extends Controller
{
    public function index()
    {
        if (auth()->user()?->can_work_without_device) {
            abort(403);
        }

        $filters = $this->getTableFilters();

        $smsLogs = SmsLog::query()
            ->whereRelation('user', 'id', auth()->id())
            ->whereNotNull('parsing_result')
            ->with(['device', 'order'])
            ->when($filters->search, function ($query) use ($filters) {
                $query->where('message', 'like', '%' . strtolower($filters->search) . '%');
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $smsLogs = SmsLogResource::collection($smsLogs);

        return Inertia::render('SmsLog/Index', compact('smsLogs', 'filters'));
    }
}
