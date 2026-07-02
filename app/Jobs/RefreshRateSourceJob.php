<?php

namespace App\Jobs;

use App\Models\RateSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshRateSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $rateSourceId)
    {
        $this->afterCommit();
        $this->onQueue('conversion-prices-parser');
    }

    public function handle(): void
    {
        $source = RateSource::query()->find($this->rateSourceId);

        if (! $source) {
            return;
        }

        try {
            services()->market()->refreshSource($source);
        } catch (\Throwable $e) {
            logger()->error('RefreshRateSourceJob failed for source '.$this->rateSourceId);
            report($e);
        }
    }
}
