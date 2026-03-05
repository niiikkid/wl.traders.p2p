<?php

namespace App\Http\Controllers;

use App\Enums\DetailType;
use App\Enums\DisputeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutMethodType;
use App\Enums\PayoutStatus;
use App\Models\Merchant;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Services\Money\Currency;
use Carbon\Carbon;

abstract class Controller
{
    public function getTableFilters(): TableFiltersValue
    {
        $currentRoute = request()->route()->getName();
        $sessionKey = 'table_filters_' . $currentRoute;

        // Проверяем, что это GET-запрос
        if (request()->isMethod('GET')) {
            // Если запрос пустой, пытаемся загрузить сохраненные параметры из сессии
            if (empty(request()->all())) {
                $savedFilters = session($sessionKey);
                if ($savedFilters) {
                    // Перенаправляем на этот же роут, но с сохраненными параметрами без возврата
                    header('Location: ' . route($currentRoute, $savedFilters));
                    exit();
                }
            } else {
                // Сохраняем текущие параметры запроса в сессию для этого роута
                session([$sessionKey => request()->all()]);
            }
        }

        $orderStatuses = request()->input('filters.orderStatuses', '');
        $orderStatuses = explode(',', $orderStatuses);

        foreach ($orderStatuses as $key => $value) {
            if (! OrderStatus::tryFrom($value)) {
                unset($orderStatuses[$key]);
            }
        }

        $disputeStatuses = request()->input('filters.disputeStatuses', '');
        $disputeStatuses = explode(',', $disputeStatuses);

        foreach ($disputeStatuses as $key => $value) {
            if (! DisputeStatus::tryFrom($value)) {
                unset($disputeStatuses[$key]);
            }
        }

        $invoiceStatuses = request()->input('filters.invoiceStatuses', '');
        $invoiceStatuses = explode(',', $invoiceStatuses);

        foreach ($invoiceStatuses as $key => $value) {
            if (! InvoiceStatus::tryFrom($value)) {
                unset($invoiceStatuses[$key]);
            }
        }

        $apiLogStatuses = request()->input('filters.apiLogStatuses', '');
        $apiLogStatuses = explode(',', $apiLogStatuses);

        foreach ($apiLogStatuses as $key => $value) {
            if (! in_array($value, [0, 1])) {
                unset($apiLogStatuses[$key]);
            }
        }

        $orderStatuses = request()->input('filters.orderStatuses', '');
        $orderStatuses = explode(',', $orderStatuses);

        foreach ($orderStatuses as $key => $value) {
            if (! OrderStatus::tryFrom($value)) {
                unset($orderStatuses[$key]);
            }
        }

        $detailTypes = request()->input('filters.detailTypes', '');
        $detailTypes = explode(',', $detailTypes);

        foreach ($detailTypes as $key => $value) {
            if (! DetailType::tryFrom($value)) {
                unset($detailTypes[$key]);
            }
        }

        $roles = request()->input('filters.roles', '');
        $roles = explode(',', $roles);

        $roles = array_filter($roles);

        $payoutStatuses = request()->input('filters.payoutStatuses', '');
        $payoutStatuses = explode(',', $payoutStatuses);

        foreach ($payoutStatuses as $key => $value) {
            if (! PayoutStatus::tryFrom($value)) {
                unset($payoutStatuses[$key]);
            }
        }

        $payoutMethodTypes = request()->input('filters.payoutMethodTypes', '');
        $payoutMethodTypes = explode(',', $payoutMethodTypes);

        foreach ($payoutMethodTypes as $key => $value) {
            if (! PayoutMethodType::tryFrom($value)) {
                unset($payoutMethodTypes[$key]);
            }
        }

        $merchantIds = request()->input('filters.merchantIds', '');
        $merchantIds = explode(',', $merchantIds);
        $merchantIds = array_filter($merchantIds, fn ($value) => $value !== '');
        $merchantIds = array_map('intval', $merchantIds);
        $merchantIds = array_values(array_unique(array_filter($merchantIds)));

        $startDate = request()->input('filters.startDate');

        if ($startDate) {
            $startDate = str_replace('.', '/', $startDate);
            $startDate = Carbon::createFromFormat('d/m/Y', $startDate);
        }

        $endDate = request()->input('filters.endDate');
        if ($endDate) {
            $endDate = str_replace('.', '/', $endDate);
            $endDate = Carbon::createFromFormat('d/m/Y', $endDate);
        }

        if ($startDate && $endDate?->lessThan($startDate)) {
            $endDate = null;
        }

        $externalID = request()->input('filters.externalID');
        $uuid = request()->input('filters.uuid');
        $paymentGateway = request()->input('filters.paymentGateway');
        $clientId = request()->input('filters.clientId');
        $orderUuid = request()->input('filters.orderUuid');

        $currentFilters = [
            'orderStatuses' => $orderStatuses,
            'disputeStatuses' => $disputeStatuses,
            'invoiceStatuses' => $invoiceStatuses,
            'apiLogStatuses' => $apiLogStatuses,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'externalID' => $externalID,
            'uuid' => $uuid,
            'orderUuid' => $orderUuid,
            'search' => request()->input('filters.search'),
            'onlySuccessParsing' => request()->input('filters.onlySuccessParsing') === 'true',
            'amount' => request()->input('filters.amount'),
            'minAmount' => request()->input('filters.minAmount'),
            'maxAmount' => request()->input('filters.maxAmount'),
            'paymentDetail' => request()->input('filters.paymentDetail'),
            'user' => request()->input('filters.user'),
            'id' => request()->input('filters.id'),
            'name' => request()->input('filters.name'),
            'clientId' => $clientId,
            'active' => request()->input('filters.active') === 'true',
            'multipliedDetails' => request()->input('filters.multipliedDetails') === 'true',
            'online' => request()->input('filters.online') === 'true',
            'address' => request()->input('filters.address'),
            'merchantIds' => $merchantIds,
            'merchant' => request()->input('filters.merchant'),
            'currency' => request()->input('filters.currency'),
            'method' => request()->input('filters.method'),
            'traffic_disabled' => request()->input('filters.traffic_disabled') === 'true',
            'roles' => $roles,
            'detailTypes' => $detailTypes,
            'paymentGateway' => $paymentGateway,
            'payoutStatuses' => array_values($payoutStatuses),
            'payoutMethodTypes' => array_values($payoutMethodTypes),
        ];

        return new TableFiltersValue(
            startDate: $currentFilters['startDate'],
            endDate: $currentFilters['endDate'],
            orderStatuses: $currentFilters['orderStatuses'],
            disputeStatuses: $currentFilters['disputeStatuses'],
            invoiceStatuses: $currentFilters['invoiceStatuses'],
            apiLogStatuses: $currentFilters['apiLogStatuses'],
            externalID: $currentFilters['externalID'],
            uuid: $currentFilters['uuid'],
            orderUuid: $currentFilters['orderUuid'],
            search: $currentFilters['search'],
            onlySuccessParsing: $currentFilters['onlySuccessParsing'],
            amount: $currentFilters['amount'],
            minAmount: $currentFilters['minAmount'],
            maxAmount: $currentFilters['maxAmount'],
            paymentDetail: $currentFilters['paymentDetail'],
            user: $currentFilters['user'],
            id: $currentFilters['id'],
            name: $currentFilters['name'],
            clientId: $currentFilters['clientId'],
            active: $currentFilters['active'],
            multipliedDetails: $currentFilters['multipliedDetails'],
            online: $currentFilters['online'],
            address: $currentFilters['address'],
            merchantIds: $currentFilters['merchantIds'],
            merchant: $currentFilters['merchant'],
            currency: $currentFilters['currency'],
            method: $currentFilters['method'],
            traffic_disabled: $currentFilters['traffic_disabled'],
            roles: $currentFilters['roles'],
            detailTypes: $currentFilters['detailTypes'],
            paymentGateway: $currentFilters['paymentGateway'],
            payoutStatuses: $currentFilters['payoutStatuses'],
            payoutMethodTypes: $currentFilters['payoutMethodTypes'],
        );
    }

    public function getFiltersData(): array
    {
        $orderStatuses = [];
        foreach (OrderStatus::values() as $status) {
            $orderStatuses[] = [
                'name' => trans("order.status.{$status}"),
                'value' => $status,
            ];
        }

        $disputeStatuses = [];
        foreach (DisputeStatus::values() as $status) {
            $disputeStatuses[] = [
                'name' => trans("dispute.status.{$status}"),
                'value' => $status,
            ];
        }

        $invoiceStatuses = [];
        foreach (InvoiceStatus::values() as $status) {
            $invoiceStatuses[] = [
                'name' => trans("invoice.status.{$status}"),
                'value' => $status,
            ];
        }

        $apiLogStatuses = [
            [
                'name' => 'Успешные',
                'value' => '1',
            ],
            [
                'name' => 'Неуспешные',
                'value' => '0',
            ],
        ];

        $detailTypes = [];
        foreach (DetailType::values() as $type) {
            $detailTypes[] = [
                'name' => trans("detail-type.{$type}"),
                'value' => $type,
            ];
        }

        // Получаем список всех ролей из БД
        $roles = \Spatie\Permission\Models\Role::all()
            ->map(function ($role) {
                return [
                    'name' => $role->name,
                    'value' => $role->name,
                ];
            })
            ->toArray();

        $merchantItems = [];
        $user = auth()->user();

        if ($user) {
            if ($user->hasRole('Super Admin')) {
                $merchantQuery = Merchant::query();
            } elseif ($user->hasRole('Support') || $user->hasRole('Merchant Support')) {
                $merchantQuery = $user->merchants();
            } else {
                $merchantQuery = Merchant::query()->where('user_id', $user->id);
            }

            $merchantItems = $merchantQuery
                ->select('merchants.id', 'merchants.name')
                ->orderBy('merchants.name')
                ->get()
                ->map(function (Merchant $merchant) {
                    return [
                        'name' => $merchant->name,
                        'value' => (string) $merchant->id,
                    ];
                })
                ->toArray();
        }

        $payoutStatuses = [];
        foreach (PayoutStatus::cases() as $status) {
            $payoutStatuses[] = [
                'name' => match ($status) {
                    PayoutStatus::OPEN => 'Открыта',
                    PayoutStatus::TAKEN => 'В работе',
                    PayoutStatus::SENT => 'Отправлено',
                    PayoutStatus::COMPLETED => 'Завершена',
                    PayoutStatus::CANCELED => 'Отменена',
                },
                'value' => $status->value,
            ];
        }

        $payoutMethodTypes = [];
        foreach (PayoutMethodType::cases() as $type) {
            $payoutMethodTypes[] = [
                'name' => match ($type) {
                    PayoutMethodType::SBP => 'СБП',
                    PayoutMethodType::CARD => 'Карта',
                },
                'value' => $type->value,
            ];
        }

        $currencyVariants = Currency::getAll()
            ->map(function (Currency $currency) {
                $code = strtolower($currency->getCode());

                return [
                    'name' => strtoupper($code),
                    'value' => $code,
                ];
            })
            ->values()
            ->toArray();

        return [
            'orderStatuses' => $orderStatuses,
            'disputeStatuses' => $disputeStatuses,
            'invoiceStatuses' => $invoiceStatuses,
            'apiLogStatuses' => $apiLogStatuses,
            'currency' => $currencyVariants,
            'roles' => $roles,
            'detailTypes' => $detailTypes,
            'merchantIds' => $merchantItems,
            'payoutStatuses' => $payoutStatuses,
            'payoutMethodTypes' => $payoutMethodTypes,
        ];
    }
}
