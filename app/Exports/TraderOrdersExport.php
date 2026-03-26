<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TraderOrdersExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    public function __construct(
        private readonly User $trader,
        private readonly ?Carbon $dateFrom = null,
        private readonly ?Carbon $dateTo = null,
    ) {}

    public function query(): Builder
    {
        $query = Order::query()
            ->with(['merchant.user', 'paymentDetail', 'paymentGateway'])
            ->where('trader_id', $this->trader->id)
            ->orderByDesc('id');

        if ($this->dateFrom && $this->dateTo) {
            $query->whereBetween('created_at', [$this->dateFrom, $this->dateTo]);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Creation date',
            'Finish date',
            'Internal id',
            'Requisite',
            'Method',
            'Status',
            'Amount',
            'Currency',
            'Crypto amount',
            'Trader profit',
        ];
    }

    public function map($row): array
    {
        /** @var Order $row */
        return [
            (string) $row->created_at?->toDateTimeString(),
            (string) $row->finished_at?->toDateTimeString(),
            (string) $row->uuid,
            (string) ($row->paymentDetail?->detail ?? ''),
            (string) ($row->paymentGateway?->name ?? $row->paymentGateway?->code ?? ''),
            (string) $row->status->value,
            (string) ($row->amount?->toBeauty() ?? ''),
            (string) ($row->currency?->getCode() ?? ''),
            (string) ($row->total_profit?->toBeauty() ?? ''),
            (string) ($row->trader_profit?->toBeauty() ?? ''),
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
        ];
    }
}
