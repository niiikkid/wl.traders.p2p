<?php

namespace App\Jobs;

use App\Enums\MarketEnum;
use App\Services\Money\Currency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadConversionPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Currency   $currency,
        private MarketEnum $market,
    )
    {
        $this->afterCommit();
        $this->onQueue('conversion-prices-parser');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            services()->market()->loadPricesFor($this->currency, $this->market);
        } catch (\Throwable $e) {
            logger()->error('Currency: ' . $this->currency->getCode() . ' Market: ' . $this->market->value);
            report($e);
        }
    }
}
