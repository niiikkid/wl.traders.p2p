<?php

namespace App\Http\Controllers\Trader;

use App\Exports\TraderOrdersExport;
use App\Exports\TraderPayoutsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trader\Export\ExportRangeRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportOrders(ExportRangeRequest $request)
    {
        $this->ensureTrader($request->user());

        return Excel::download(
            new TraderOrdersExport(
                $request->user(),
                $request->dateFrom(),
                $request->dateTo(),
            ),
            now()->format('Y-m-d_H-i-s').'_trader-orders.xlsx'
        );
    }

    public function exportPayouts(ExportRangeRequest $request)
    {
        $this->ensureTrader($request->user());

        return Excel::download(
            new TraderPayoutsExport(
                $request->user(),
                $request->dateFrom(),
                $request->dateTo(),
            ),
            now()->format('Y-m-d_H-i-s').'_trader-payouts.xlsx'
        );
    }

    private function ensureTrader(User $user): void
    {
        if (! $user->hasAnyRole(['Trader', 'Super Admin'])) {
            abort(Response::HTTP_FORBIDDEN, 'Экспорт доступен только трейдерам и администраторам.');
        }
    }
}
