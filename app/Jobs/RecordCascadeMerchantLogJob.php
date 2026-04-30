<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CascadeMerchantLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordCascadeMerchantLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private readonly array $attributes)
    {
        $this->afterCommit();
        $this->onQueue('callback');
    }

    public function handle(): void
    {
        CascadeMerchantLog::query()->create($this->attributes);
    }
}
