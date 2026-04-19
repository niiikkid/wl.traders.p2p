<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\BalanceType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AdminWalletTransactionsExport implements FromQuery, WithColumnFormatting, WithCustomStartCell, WithEvents, WithHeadings, WithMapping
{
    private const string EXPORT_LOCALE = 'ru';

    /** Объединение подписи по числу колонок данных (A–F). */
    private const string SUMMARY_MERGE_RANGE = 'A1:F1';

    public function __construct(
        private readonly Wallet $wallet,
        private readonly BalanceType $balanceType,
    ) {}

    public function query(): Builder
    {
        return Transaction::query()
            ->where('wallet_id', $this->wallet->id)
            ->where('balance_type', $this->balanceType)
            ->orderByDesc('id');
    }

    public function startCell(): string
    {
        return 'A2';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Дата и время',
            'Сумма',
            'Валюта',
            'Направление',
            'Тип операции',
        ];
    }

    /**
     * @param  Transaction  $row
     */
    public function map($row): array
    {
        $locale = self::EXPORT_LOCALE;

        return [
            (string) $row->id,
            $row->created_at?->format('d.m.Y H:i:s') ?? '',
            $row->amount->toBeauty(),
            mb_strtoupper((string) $row->amount->getCurrency()->getCode()),
            __('transaction-direction.'.$row->direction->value, [], $locale),
            __('transaction-type.'.$row->type->value, [], $locale),
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
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells(self::SUMMARY_MERGE_RANGE);
                $sheet->setCellValue('A1', $this->walletSummaryLine());

                $alignment = $sheet->getStyle('A1')->getAlignment();
                $alignment->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $alignment->setVertical(Alignment::VERTICAL_CENTER);
                $alignment->setWrapText(true);

                $sheet->getRowDimension(1)->setRowHeight(36);
            },
        ];
    }

    private function walletSummaryLine(): string
    {
        $this->wallet->loadMissing('user');

        return sprintf(
            'Транзакции по кошельку (%s) — пользователь: %s',
            $this->exportedBalanceKindLabel(),
            $this->wallet->user?->email ?? '—',
        );
    }

    private function exportedBalanceKindLabel(): string
    {
        return match ($this->balanceType) {
            BalanceType::TRUST => 'Траст',
            BalanceType::MERCHANT => 'Мерчант',
            BalanceType::TEAMLEADER => 'Тимлид',
            BalanceType::COMMISSION => 'Комиссия',
        };
    }
}
