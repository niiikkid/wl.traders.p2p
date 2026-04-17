<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PayoutMethodType;
use App\Enums\PayoutOperationType;
use App\Enums\PayoutStatus;
use App\Models\Payout\Payout;
use App\Models\Payout\PayoutOperation;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AdminPayoutsExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting
{
    private const int COMMISSION_PERCENT_DECIMALS = 2;

    public function __construct(
        private readonly TableFiltersValue $filters,
    ) {}

    public function query(): Builder
    {
        return queries()->payout()->queryForAdminExport($this->filters);
    }

    public function headings(): array
    {
        return [
            'ID',
            'UUID',
            'External ID',
            'Реквизиты',
            'Получатель (инициалы)',
            'Тип реквизита',
            'Банк / метод (отображение)',
            'Платёжный метод (название)',
            'Платёжный метод (код)',
            'Валюта шлюза',
            'Сумма клиенту',
            'Валюта суммы клиенту',
            'Тело USDT',
            'Валюта тела USDT',
            'Списано у мерчанта',
            'Валюта списания мерчанта',
            'Получит трейдер',
            'Валюта зачисления трейдеру',
            'Комиссия всего',
            'Комиссия трейдер',
            'Комиссия тимлид',
            'Комиссия сервис',
            'Валюта комиссий',
            'Ставка всего %',
            'Ставка трейдер %',
            'Ставка тимлид %',
            'Курс (маркет)',
            'Курс (цена)',
            'Валюта курса',
            'Курс зафиксирован',
            'Статус (код)',
            'Статус',
            'Мерчант (название)',
            'Мерчант (email владельца)',
            'Трейдер (email)',
            'Создано',
            'Истекает',
            'Взято',
            'Отправлено',
            'Холд до',
            'Завершено',
            'Отменено',
            'Операции',
        ];
    }

    /**
     * @param  Payout  $row
     */
    public function map($row): array
    {
        $bankDisplay = $row->bank_name
            ?? $row->paymentGateway?->name
            ?? '';

        $feeCurrency = strtoupper(
            (string) ($row->total_fee?->getCurrency()->getCode()
                ?? $row->trader_fee?->getCurrency()->getCode()
                ?? 'usdt')
        );

        $rateCurrency = strtoupper(
            (string) ($row->conversion_price?->getCurrency()->getCode() ?? 'usdt')
        );

        return [
            (string) $row->id,
            (string) $row->uuid,
            (string) ($row->external_id ?? ''),
            (string) $row->requisites,
            (string) ($row->initials ?? ''),
            $this->methodTypeLabel($row->payout_method_type),
            (string) $bankDisplay,
            (string) ($row->paymentGateway?->name ?? ''),
            (string) ($row->paymentGateway?->code ?? ''),
            (string) ($row->paymentGateway?->currency?->getCode()
                ? strtoupper($row->paymentGateway->currency->getCode())
                : ''),
            $this->moneyAmount($row->amount_fiat),
            $this->moneyCurrency($row->amount_fiat),
            $this->moneyAmount($row->usdt_body),
            $this->moneyCurrency($row->usdt_body),
            $this->moneyAmount($row->merchant_debit),
            $this->moneyCurrency($row->merchant_debit),
            $this->moneyAmount($row->trader_credit),
            $this->moneyCurrency($row->trader_credit),
            $this->moneyAmount($row->total_fee),
            $this->moneyAmount($row->trader_fee),
            $this->moneyAmount($row->teamlead_fee),
            $this->moneyAmount($row->service_fee),
            $feeCurrency,
            $this->percentValue($row->total_commission_rate),
            $this->percentValue($row->trader_commission_rate),
            $this->percentValue($row->teamlead_commission_rate),
            (string) ($row->rate_market?->value ?? ''),
            $this->moneyAmount($row->conversion_price),
            $rateCurrency,
            (string) ($row->rate_fixed_at?->format('Y-m-d H:i:s') ?? ''),
            (string) $row->status->value,
            $this->statusLabel($row->status),
            (string) ($row->merchant?->name ?? ''),
            (string) ($row->merchant?->user?->email ?? ''),
            (string) ($row->trader?->email ?? ''),
            (string) ($row->created_at?->format('Y-m-d H:i:s') ?? ''),
            (string) ($row->expires_at?->format('Y-m-d H:i:s') ?? ''),
            (string) ($row->taken_at?->format('Y-m-d H:i:s') ?? ''),
            (string) ($row->sent_at?->format('Y-m-d H:i:s') ?? ''),
            (string) ($row->hold_until?->format('Y-m-d H:i:s') ?? ''),
            (string) ($row->completed_at?->format('Y-m-d H:i:s') ?? ''),
            (string) ($row->canceled_at?->format('Y-m-d H:i:s') ?? ''),
            $this->formatOperations($row),
        ];
    }

    public function columnFormats(): array
    {
        $count = count($this->headings());
        $formats = [];
        for ($i = 1; $i <= $count; $i++) {
            $formats[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
    }

    private function moneyAmount(?Money $money): string
    {
        if ($money === null) {
            return '';
        }

        $precision = $money->getCurrency()->getPrecision();
        $amount = $money->toPrecision();

        if (! str_contains($amount, '.')) {
            return $amount.'.'.str_repeat('0', $precision);
        }

        [$integer, $fraction] = explode('.', $amount, 2);
        $fraction = str_pad(substr($fraction, 0, $precision), $precision, '0');

        return $integer.'.'.$fraction;
    }

    private function moneyCurrency(?Money $money): string
    {
        if ($money === null) {
            return '';
        }

        return strtoupper($money->getCurrency()->getCode());
    }

    private function percentValue(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format($value, self::COMMISSION_PERCENT_DECIMALS, '.', '');
    }

    private function statusLabel(PayoutStatus $status): string
    {
        return match ($status) {
            PayoutStatus::OPEN => 'Открыта',
            PayoutStatus::TAKEN => 'В работе',
            PayoutStatus::SENT => 'Отправлено',
            PayoutStatus::COMPLETED => 'Завершена',
            PayoutStatus::CANCELED => 'Отменена',
        };
    }

    private function methodTypeLabel(PayoutMethodType $type): string
    {
        return match ($type) {
            PayoutMethodType::SBP => 'СБП',
            PayoutMethodType::CARD => 'Карта',
        };
    }

    private function operationTypeLabel(PayoutOperationType $type): string
    {
        return match ($type) {
            PayoutOperationType::RESERVE_FROM_MERCHANT => 'Резерв с мерчанта',
            PayoutOperationType::RETURN_TO_MERCHANT => 'Возврат мерчанту',
            PayoutOperationType::MARK_TAKEN => 'Взятие выплаты',
            PayoutOperationType::MARK_SENT => 'Отметка об отправке',
            PayoutOperationType::SET_HOLD => 'Установка холда',
            PayoutOperationType::RELEASE_HOLD => 'Снятие холда',
            PayoutOperationType::CREDIT_TRADER => 'Зачисление трейдеру',
            PayoutOperationType::SERVICE_INCOME => 'Доход сервиса',
            PayoutOperationType::TEAMLEAD_INCOME => 'Доход тимлида',
        };
    }

    private function formatOperations(Payout $payout): string
    {
        $operations = $payout->relationLoaded('operations')
            ? $payout->operations
            : $payout->operations()->orderBy('id')->get();

        if ($operations->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($operations as $operation) {
            /** @var PayoutOperation $operation */
            $label = $this->operationTypeLabel($operation->type);
            $amount = $this->moneyAmount($operation->amount);
            $currency = $this->moneyCurrency($operation->amount);
            $amountPart = trim($amount.' '.$currency);
            $at = $operation->created_at?->format('Y-m-d H:i:s') ?? '';
            $lines[] = $label.' | '.$amountPart.' | '.$at;
        }

        return implode("\n", $lines);
    }
}
