<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShadowSmsLogResource;
use App\Models\ShadowSmsLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShadowSmsLogController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $shadowSmsLogEnabled = services()->settings()->isShadowSmsLogEnabled();

        $query = ShadowSmsLog::query()
            ->with(['user', 'device'])
            ->when($filters->login, function ($query) use ($filters) {
                $query->whereHas('user', function ($query) use ($filters) {
                    $query->where('email', 'like', '%'.$filters->login.'%');
                });
            })
            ->when($filters->deviceName, function ($query) use ($filters) {
                $query->whereHas('device', function ($query) use ($filters) {
                    $query->where('name', 'like', '%'.$filters->deviceName.'%');
                });
            })
            ->when($filters->searchSender, function ($query) use ($filters) {
                $query->where('sender', 'like', '%'.$filters->searchSender.'%');
            })
            ->when($filters->searchMessage, function ($query) use ($filters) {
                $query->where('message', 'like', '%'.$filters->searchMessage.'%');
            });

        $shadowSmsLogs = $query->clone()
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $shadowSmsLogs = ShadowSmsLogResource::collection($shadowSmsLogs);
        $shadowSmsLogsTotalCount = $query->clone()->count();

        return Inertia::render('Admin/ShadowSmsLog/Index', compact(
            'shadowSmsLogs',
            'shadowSmsLogsTotalCount',
            'shadowSmsLogEnabled',
            'filters'
        ));
    }

    public function updateEnabled(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        services()->settings()->updateShadowSmsLogEnabled((bool) $validated['enabled']);

        return redirect()->back()->with(
            'message',
            $validated['enabled'] ? 'Запись в теневой лог включена.' : 'Запись в теневой лог выключена.'
        );
    }

    public function destroyAll()
    {
        ShadowSmsLog::query()->delete();

        return redirect()->back()->with('message', 'Теневой лог очищен.');
    }
}
