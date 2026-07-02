<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AntiFraudLogResource;
use App\Models\AntiFraudLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AntiFraudHistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->getTableFilters()->toArray();
        $clientId = $request->input('filters.clientId');

        $logs = AntiFraudLog::query()
            ->with('merchant:id,name,uuid')
            ->when($filters['merchant'] ?? null, function ($query, string $merchant) {
                $query->whereHas('merchant', function ($merchantQuery) use ($merchant) {
                    $merchantQuery->where('name', 'like', "%{$merchant}%")
                        ->orWhere('uuid', 'like', "%{$merchant}%");
                });
            })
            ->when($clientId, function ($query, string $clientId) {
                $query->where('client_id', 'like', "%{$clientId}%");
            })
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 10))
            ->withQueryString();

        return Inertia::render('Admin/AntiFraud/History', [
            'logs' => AntiFraudLogResource::collection($logs),
            'filters' => array_merge($filters, [
                'clientId' => $clientId,
            ]),
        ]);
    }
}
