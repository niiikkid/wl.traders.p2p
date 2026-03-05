<?php

namespace App\Queries\Cache;

use App\Queries\Interfaces\MerchantQueries;
use App\Queries\Eloquent\MerchantQueriesEloquent;
use App\Models\Merchant;
use Illuminate\Support\Facades\Cache;

class MerchantQueriesCache implements MerchantQueries
{
    private MerchantQueriesEloquent $eloquentQueries;
    private int $cacheTtl;

    protected array $merchantByUUID = [];
    protected array $merchantByID = [];

    public function __construct(MerchantQueriesEloquent $eloquentQueries, int $cacheTtl = 3600)
    {
        $this->eloquentQueries = $eloquentQueries;
        $this->cacheTtl = $cacheTtl;
    }

    public function findByUUID(string $uuid): ?Merchant
    {
        if (empty($this->merchantByUUID[$uuid])) {
            $cacheKey = "get_merchant_by_uuid_{$uuid}";

            $merchant = Cache::remember($cacheKey, $this->cacheTtl, function () use ($uuid) {
                return $this->eloquentQueries->findByUUID($uuid);
            });

            $this->merchantByUUID[$uuid] = $merchant;
            if ($merchant) {
                $this->merchantByID[$merchant->id] = $merchant;
            }
        }

        return $this->merchantByUUID[$uuid];
    }

    public function findByID(string $id): ?Merchant
    {
        if (empty($this->merchantByID[$id])) {
            $cacheKey = "get_merchant_by_id_{$id}";

            $merchant = Cache::remember($cacheKey, $this->cacheTtl, function () use ($id) {
                return $this->eloquentQueries->findByID($id);
            });

            $this->merchantByID[$id] = $merchant;
            if ($merchant) {
                $this->merchantByUUID[$merchant->uuid] = $merchant;
            }
        }

        return $this->merchantByID[$id];
    }
}
