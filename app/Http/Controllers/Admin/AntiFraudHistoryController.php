<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AntiFraudLogResource;
use App\Models\AntiFraudLog;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AntiFraudHistoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->getTableFilters()->toArray();
        $clientId = $request->input('filters.clientId');

        $baseQuery = AntiFraudLog::query()
            ->with('merchant:id,name,uuid')
            ->when($filters['merchant'] ?? null, function ($query, string $merchant) {
                $query->whereHas('merchant', function ($merchantQuery) use ($merchant) {
                    $merchantQuery->where('name', 'like', "%{$merchant}%")
                        ->orWhere('uuid', 'like', "%{$merchant}%");
                });
            })
            ->when($clientId, function ($query, string $clientId) {
                $query->where('client_id', 'like', "%{$clientId}%");
            });

        $logs = (clone $baseQuery)
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 10))
            ->withQueryString();

        $logs = AntiFraudLogResource::collection($logs);

        $now = now();
        $chartStart = $now->copy()->subHours(23)->startOfHour();
        $chartEnd = $now->copy()->startOfHour();

        $chartBaseQuery = (clone $baseQuery)
            ->whereBetween('created_at', [$chartStart, $chartEnd->copy()->endOfHour()]);

        $uniqueCounts = (clone $chartBaseQuery)
            ->whereNotNull('client_id')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(DISTINCT client_id) as unique_count")
            ->groupBy('hour')
            ->pluck('unique_count', 'hour');

        $hourlyClients = (clone $chartBaseQuery)
            ->whereNotNull('client_id')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, client_id, COUNT(*) as total")
            ->groupBy('hour', 'client_id');

        $repeatedCounts = DB::query()
            ->fromSub($hourlyClients, 'hourly_clients')
            ->selectRaw('hour, COUNT(*) as repeated_count')
            ->where('total', '>=', 2)
            ->groupBy('hour')
            ->pluck('repeated_count', 'hour');

        $blockedCounts = (clone $chartBaseQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, SUM(CASE WHEN decision != 'allow' THEN 1 ELSE 0 END) as blocked_count")
            ->groupBy('hour')
            ->pluck('blocked_count', 'hour');

        $labels = [];
        $uniqueSeries = [];
        $repeatedSeries = [];
        $blockedSeries = [];

        foreach (CarbonPeriod::create($chartStart, '1 hour', $chartEnd) as $hour) {
            $hourKey = $hour->format('Y-m-d H:00:00');
            $labels[] = $hour->format('H:i');
            $uniqueSeries[] = (int) ($uniqueCounts[$hourKey] ?? 0);
            $repeatedSeries[] = (int) ($repeatedCounts[$hourKey] ?? 0);
            $blockedSeries[] = (int) ($blockedCounts[$hourKey] ?? 0);
        }

        return Inertia::render('Admin/AntiFraud/History', [
            'logs' => $logs,
            'filters' => array_merge($filters, [
                'clientId' => $clientId,
            ]),
            'chart' => [
                'labels' => $labels,
                'series' => [
                    [
                        'name' => 'Уникальные клиенты',
                        'data' => $uniqueSeries,
                    ],
                    [
                        'name' => 'Повторные клиенты',
                        'data' => $repeatedSeries,
                    ],
                    [
                        'name' => 'Блокировки',
                        'data' => $blockedSeries,
                    ],
                ],
            ],
        ]);
    }
}
