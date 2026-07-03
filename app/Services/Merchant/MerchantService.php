<?php

declare(strict_types=1);

namespace App\Services\Merchant;

use App\Contracts\MerchantServiceContract;
use App\DTO\Merchant\MerchantCreateDTO;
use App\Enums\MarketEnum;
use App\Models\Merchant;
use App\Services\Money\Currency;
use App\Utils\Transaction;
use Illuminate\Support\Str;

class MerchantService implements MerchantServiceContract
{
    public function create(MerchantCreateDTO $data): Merchant
    {
        return Transaction::run(function () use ($data): Merchant {
            $defaultGeo = [
                Currency::RUB()->getCode() => MarketEnum::BYBIT->value,
            ];

            $merchant = Merchant::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $data->user_id,
                'active' => true,
                'name' => $data->name,
                'description' => (string) ($data->description ?? ''),
                'domain' => $data->project_link ? parse_url($data->project_link)['host'] ?? '' : '',
                'settings' => ['geos' => $defaultGeo],
                'gateway_settings' => [],
            ]);

            services()->wallet()->createForMerchant($merchant->load('user'));

            return $merchant;
        });
    }
}
