<?php

namespace App\Http\Controllers\Trader;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentDetailResource;
use App\Http\Resources\TraderDetailStatisticsResource;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class StatisticController extends Controller
{
    public function index(Request $request)
    {
        // Получаем месяц из запроса или устанавливаем значение по умолчанию
        $selectedMonth = $request->input('month', now()->format('Y-m'));

        // Получаем активный тип графика или устанавливаем значение по умолчанию
        $chartType = $request->input('chartType', 'turnover');

        // Получаем активный тип таблицы или устанавливаем значение по умолчанию
        $tableType = $request->input('tableType', 'payment-details');

        // Разбиваем строку месяца на год и месяц
        [$year, $month] = array_pad(explode('-', $selectedMonth), 2, null);
        $year = (int) $year;
        $month = (int) $month;

        // Получаем платежные реквизиты трейдера с пагинацией
        $paymentDetails = PaymentDetail::with(['paymentGateways'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(request()->per_page ?? 10);

        // Получаем все успешные заказы трейдера за выбранный месяц
        $monthlyOrders = Order::where('status', OrderStatus::SUCCESS)
            ->whereNotNull('trader_paid_for_order')
            ->whereIn('payment_detail_id', $paymentDetails->pluck('id'))
            ->get();

        // Группируем заказы по payment_detail_id
        $ordersByPaymentDetail = $monthlyOrders->groupBy('payment_detail_id');

        // Преобразуем коллекцию платежных реквизитов с добавлением статистики
        $paymentDetailsCollection = $paymentDetails->getCollection()->map(function ($detail) use ($ordersByPaymentDetail) {
            $detailOrders = $ordersByPaymentDetail[$detail->id] ?? collect();

            // Расчет оборота за месяц
            $turnover = $detailOrders->sum(function ($order) {
                return $order->total_profit->toInt();
            });

            // Количество сделок за месяц
            $ordersCount = $detailOrders->count();

            // Добавляем статистику к реквизиту
            $detail->monthly_turnover = $turnover;
            $detail->monthly_orders_count = $ordersCount;

            return $detail;
        });

        // Заменяем коллекцию в пагинаторе
        $paymentDetails->setCollection($paymentDetailsCollection);

        // Преобразуем в ресурс, используя новый ресурс TraderDetailStatisticsResource
        $paymentDetails = TraderDetailStatisticsResource::collection($paymentDetails);

        // Получаем закрытые сделки трейдера с пагинацией
        $closedOrders = Order::with(['paymentDetail', 'paymentGateway'])
            ->whereRelation('paymentDetail', 'user_id', auth()->id())
            ->whereNotNull('trader_paid_for_order')
            ->where('status', OrderStatus::SUCCESS)
            ->orderBy('created_at', 'desc')
            ->paginate(request()->per_page ?? 10);

        $closedOrders = OrderResource::collection($closedOrders);

        // Получаем данные для графиков
        $chartData = $this->getChartData($selectedMonth);

        // Определяем предыдущий и следующий месяцы для навигации
        $prevMonth = Carbon::create($year, $month, 1)->subMonth()->format('Y-m');
        $nextMonth = Carbon::create($year, $month, 1)->addMonth()->format('Y-m');

        return Inertia::render('Statistic/Trader/Index', [
            'paymentDetails' => $paymentDetails,
            'closedOrders' => $closedOrders,
            'chartData' => $chartData,
            'currentMonth' => $selectedMonth,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'chartType' => $chartType,
            'tableType' => $tableType,
        ]);
    }

    /**
     * Получение данных для графиков
     *
     * @param string|null $selectedMonth Выбранный месяц в формате Y-m
     * @return array
     */
    private function getChartData(?string $selectedMonth = null): array
    {
        // Если месяц не передан, используем текущий месяц
        $selectedMonth = $selectedMonth ?? now()->format('Y-m');

        $isCurrentMonth = $selectedMonth === now()->format('Y-m');
        $cacheTime = $isCurrentMonth ? 60 : (60 * 24); // 1 час для текущего месяца, 24 часа для других месяцев

        $cacheKey = "trader_stats_" . auth()->id() . "_" . $selectedMonth;

        return Cache::remember($cacheKey, $cacheTime, function () use ($selectedMonth) {
            // Разбиваем строку месяца на год и месяц
            [$year, $month] = array_pad(explode('-', $selectedMonth), 2, null);

            // Создаем Carbon объекты для начала и конца месяца
            $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfDay();
            $endDate = Carbon::create((int) $year, (int) $month, 1)->endOfMonth()->endOfDay();

            // Массив для хранения дат
            $dates = [];
            $currentDate = clone $startDate;

            // Заполняем массив дат от начала до конца месяца
            while ($currentDate <= $endDate) {
                $dates[] = $currentDate->format('Y-m-d');
                $currentDate->addDay();
            }

            // Получаем успешные заказы трейдера за этот месяц
            $orders = Order::whereRelation('paymentDetail', 'user_id', auth()->id())
                ->whereNotNull('trader_paid_for_order')
                ->where('status', OrderStatus::SUCCESS)
                ->whereBetween('created_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
                ->get();

            // Группируем заказы по дате
            $ordersByDate = $orders->groupBy(function ($order) {
                return $order->created_at->format('Y-m-d');
            });

            // Инициализация массивов данных для графиков
            $turnoverData = [];
            $incomeData = [];
            $ordersCountData = [];

            // Проходим по всем датам месяца и заполняем массивы данными
            foreach ($dates as $date) {
                $dailyOrders = $ordersByDate[$date] ?? collect();

                // Оборот (Total Profit)
                $turnoverData[] = Money::fromUnits($dailyOrders->sum(function ($order) {
                    return $order->total_profit->toUnits();
                }), Currency::USDT())->toInt();

                // Доход (Trader Profit)
                $incomeData[] = Money::fromUnits($dailyOrders->sum(function ($order) {
                    return $order->trader_profit->toUnits();
                }), Currency::USDT())->toInt();

                // Количество сделок
                $ordersCountData[] = $dailyOrders->count();
            }

            // Форматируем даты для отображения на графике (только числа дней)
            $labels = array_map(function ($date) {
                return Carbon::parse($date)->format('d');
            }, $dates);

            // Полные даты для тултипов
            $fullDates = array_map(function ($date) {
                return Carbon::parse($date)->format('d.m.Y');
            }, $dates);

            // Суммарные значения для статистики
            $totalOrders = array_sum($ordersCountData);
            $totalIncome = array_sum($incomeData);
            $totalTurnover = array_sum($turnoverData);

            return [
                'labels' => $labels,
                'fullDates' => $fullDates,
                'ordersCountData' => $ordersCountData,
                'incomeData' => $incomeData,
                'turnoverData' => $turnoverData,
                'totalOrders' => $totalOrders,
                'totalIncome' => $totalIncome,
                'totalTurnover' => $totalTurnover
            ];
        });
    }
}
