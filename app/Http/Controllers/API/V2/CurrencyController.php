<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Services\Money\Currency;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $currencies = Currency::getAll()
            ->transform(function ($currency) {
                return [
                    'currency' => $currency->getCode(),
                    'precision' => $currency->getPrecision(),
                    'symbol' => $currency->getSymbol(),
                    'name' => $currency->getName(),
                ];
            });

        return response()->success($currencies);
    }
}
