<?php

namespace App\Queries\Eloquent;

use App\Models\Merchant;
use App\Queries\Interfaces\MerchantQueries;

class MerchantQueriesEloquent implements MerchantQueries
{
    public function findByUUID(string $uuid): ?Merchant
    {
        return Merchant::where('uuid', $uuid)->first();
    }

    public function findByID(string $id): ?Merchant
    {
        return Merchant::where('id', $id)->first();
    }
}
