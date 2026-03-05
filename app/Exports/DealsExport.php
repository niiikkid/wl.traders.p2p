<?php

namespace App\Exports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DealsExport implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithColumnFormatting
{
    public function __construct(
        protected User $user,
    )
    {}

    public function startCell(): string
    {
        return 'A1';
    }

    public function collection(): Collection
    {
        return Order::query()
            ->with('paymentDetail', 'paymentGateway')
            ->whereNotNull('trader_paid_for_order')
            ->whereRelation('paymentDetail', 'user_id', $this->user->id)
            ->where('status', OrderStatus::SUCCESS)
            ->get();
    }

    public function headings(): array
    {
        return [
            [
                'UUID',
                'Сумма',
                'Валюта',
                'Сумма в USDT',
                'Курс конвертации',
                'Доход',
                'Доход в % (комиссия)',
                'Статус',
                'Платежный метод (код)',
                'Платежный метод (имя)',
                'Реквизит',
                'Имя реквизита',
                'Закрыт',
                'Создан'
            ],
            [
                'uuid',
                'amount',
                'currency',
                'amount_usdt',
                'conversion_price',
                'profit',
                'commission_rate',
                'status',
                'payment_gateway',
                'payment_gateway_name',
                'payment_detail',
                'payment_detail_name',
                'finished_at',
                'created_at'
            ]
        ];
    }

    public function map($row): array
    {
        $order = $row;
        /**
         * @var Order $order
         */
        return [
            (string) $order->uuid,
            (string) $order->amount->toBeauty(),
            (string) $order->currency->getCode(),
            (string) $order->total_profit->toBeauty(),
            (string) $order->conversion_price->toBeauty(),
            (string) $order->trader_profit->toBeauty(),
            (string) $order->trader_commission_rate,
            (string) $order->status->value,
            (string) $order->paymentGateway->code,
            (string) $order->paymentGateway->name,
            "'".$order->paymentDetail->detail,
            (string) $order->paymentDetail->name,
            (string) $order->finished_at->toDateTimeString(),
            (string) $order->created_at->toDateTimeString(),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
