<?php

namespace App\ObjectValues\TableFilters;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

class TableFiltersValue implements Arrayable
{
    public function __construct(
        public ?Carbon $startDate = null,
        public ?Carbon $endDate = null,
        public array $orderStatuses = [],
        public array $disputeStatuses = [],
        public array $invoiceStatuses = [],
        public array $apiLogStatuses = [],
        public ?string $externalID = null,
        public ?string $uuid = null,
        public ?string $orderUuid = null,
        public ?string $search = null,
        public bool $onlySuccessParsing = false,
        public ?string $amount = null,
        public ?string $minAmount = null,
        public ?string $maxAmount = null,
        public ?string $paymentDetail = null,
        public ?string $user = null,
        public ?string $id = null,
        public ?string $name = null,
        public ?string $clientId = null,
        public bool $active = false,
        public bool $multipliedDetails = false,
        public bool $online = false,
        public ?string $address = null,
        public array $merchantIds = [],
        public ?string $merchant = null,
        public ?string $currency = null,
        public ?string $method = null,
        public ?bool $status = null,
        public bool $traffic_disabled = false,
        public array $roles = [],
        public array $detailTypes = [],
        public ?string $paymentGateway = null,
        public array $payoutStatuses = [],
        public array $payoutMethodTypes = [],
    )
    {}

    public function toArray(): array
    {
        return [
            'startDate' => $this->startDate?->format('d/m/Y'),
            'endDate' => $this->endDate?->format('d/m/Y'),
            'orderStatuses' => implode(',', $this->orderStatuses),
            'disputeStatuses' => implode(',', $this->disputeStatuses),
            'invoiceStatuses' => implode(',', $this->invoiceStatuses),
            'apiLogStatuses' => implode(',', $this->apiLogStatuses),
            'externalID' => $this->externalID,
            'uuid' => $this->uuid,
            'orderUuid' => $this->orderUuid,
            'search' => $this->search,
            'onlySuccessParsing' => $this->onlySuccessParsing,
            'amount' => $this->amount,
            'minAmount' => $this->minAmount,
            'maxAmount' => $this->maxAmount,
            'paymentDetail' => $this->paymentDetail,
            'user' => $this->user,
            'id' => $this->id,
            'name' => $this->name,
            'clientId' => $this->clientId,
            'active' => $this->active,
            'multipliedDetails' => $this->multipliedDetails,
            'online' => $this->online,
            'address' => $this->address,
            'merchantIds' => implode(',', $this->merchantIds),
            'merchant' => $this->merchant,
            'currency' => $this->currency,
            'method' => $this->method,
            'status' => $this->status,
            'traffic_disabled' => $this->traffic_disabled,
            'roles' => implode(',', $this->roles),
            'detailTypes' => implode(',', $this->detailTypes),
            'paymentGateway' => $this->paymentGateway,
            'payoutStatuses' => implode(',', $this->payoutStatuses),
            'payoutMethodTypes' => implode(',', $this->payoutMethodTypes),
        ];
    }
}
