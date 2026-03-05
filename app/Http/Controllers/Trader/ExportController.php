<?php

namespace App\Http\Controllers\Trader;

use App\Exports\DealsExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportOrders(Request $request)
    {
        return Excel::download(new DealsExport($request->user()), now()->toDateTimeString().'_orders.xlsx');
    }
}
