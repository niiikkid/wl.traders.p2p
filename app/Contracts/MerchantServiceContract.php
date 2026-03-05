<?php

namespace App\Contracts;

use App\DTO\Merchant\MerchantCreateDTO;
use App\Models\Merchant;

interface MerchantServiceContract
{
    public function create(MerchantCreateDTO $data): Merchant;
}


