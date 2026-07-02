<?php

namespace App\Jobs;

use App\Models\WalletDepositInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollWalletDepositInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(private readonly int $invoiceID)
    {
        $this->onQueue('wallet-deposit');
    }

    public function handle(): void
    {
        $invoice = WalletDepositInvoice::query()->find($this->invoiceID);

        if ($invoice === null || ! $invoice->status->isOpenForPolling()) {
            return;
        }

        services()->walletDeposit()->scan($invoice);
    }
}
