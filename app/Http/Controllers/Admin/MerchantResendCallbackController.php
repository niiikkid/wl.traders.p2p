<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderCallbackJob;
use App\Models\Merchant;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MerchantResendCallbackController extends Controller
{
    public function resendByDateRange(Request $request, Merchant $merchant)
    {
        // Проверка прав доступа - только администратор
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403);
        }

        $request->validate([
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'required|date_format:d/m/Y',
        ]);

        // Преобразование дат
        $startDate = Carbon::createFromFormat('d/m/Y', $request->start_date)->startOfDay();
        $endDate = Carbon::createFromFormat('d/m/Y', $request->end_date)->endOfDay();

        // Проверка корректности диапазона дат
        if ($endDate->lessThan($startDate)) {
            return back()->withErrors(['date_range' => 'Дата окончания не может быть раньше даты начала']);
        }

        // Получаем все заказы мерчанта за указанный период
        $orders = Order::query()
            ->where('merchant_id', $merchant->id)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();

        // Отправляем callback для каждого заказа
        $count = 0;
        foreach ($orders as $order) {
            SendOrderCallbackJob::dispatch($order);
            $count++;
        }

        return back()->with('message', "Запланирована повторная отправка callback для {$count} сделок за период с {$request->start_date} по {$request->end_date}");
    }
}
