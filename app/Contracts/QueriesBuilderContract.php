<?php

namespace App\Contracts;

use App\Queries\Interfaces\DisputeQueries;
use App\Queries\Interfaces\InvoiceQueries;
use App\Queries\Interfaces\MerchantApiLogQueries;
use App\Queries\Interfaces\MerchantQueries;
use App\Queries\Interfaces\OrderQueries;
use App\Queries\Interfaces\PayoutQueries;
use App\Queries\Interfaces\PaymentDetailQueries;
use App\Queries\Interfaces\PaymentGatewayQueries;
use App\Queries\Interfaces\TransactionQueries;

interface QueriesBuilderContract
{
    public function order(): OrderQueries;

    public function paymentGateway(): PaymentGatewayQueries;

    public function paymentDetail(): PaymentDetailQueries;

    public function dispute(): DisputeQueries;

    public function merchant(): MerchantQueries;

    public function invoice(): InvoiceQueries;

    public function transaction(): TransactionQueries;
    
    public function merchantApiLog(): MerchantApiLogQueries;

    public function payout(): PayoutQueries;
}
