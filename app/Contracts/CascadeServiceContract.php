<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Models\CascadeDeal;

interface CascadeServiceContract
{
    public function createDeal(CreateCascadeDealDTO $dto): CascadeDeal;
}

