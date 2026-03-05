<?php

namespace App\Contracts;

use App\Models\Merchant;
use App\Models\MerchantClient;

interface AntiFraudServiceContract
{
    public function check(Merchant $merchant, ?string $clientId): ?MerchantClient;
}
