<?php

namespace App\Exports;

use App\Models\Payout\Payout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TraderPayoutsExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    public function __construct(
        private readonly User $trader,
        private readonly ?Carbon $dateFrom = null,
        private readonly ?Carbon $dateTo = null,
    ) {}

    public function query(): Builder
    {
        $query = Payout::query()
            ->with(['merchant.user', 'paymentGateway'])
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
        /** @var Payout $row */
        return [
            (string) $row->created_at?->toDateTimeString(),
            (string) $row->completed_at?->toDateTimeString(),
            (string) $row->uuid,
            (string) ($row->requisites ?? ''),
            (string) ($row->payout_method_type?->value ?? ''),
            (string) $row->status->value,
            (string) ($row->amount_fiat?->toBeauty() ?? ''),
            (string) strtoupper((string) ($row->amount_fiat?->getCurrency()->getCode() ?? '')),
            (string) ($row->usdt_body?->toBeauty() ?? ''),
            (string) ($row->trader_fee?->toBeauty() ?? ''),
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
