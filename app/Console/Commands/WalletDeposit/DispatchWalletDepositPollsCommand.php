<?php

namespace App\Console\Commands\WalletDeposit;

use App\Jobs\PollWalletDepositInvoiceJob;
use App\Models\WalletDepositInvoice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class DispatchWalletDepositPollsCommand extends Command
{
    protected $signature = 'wallet-deposit:invoices:dispatch-polls';

    protected $aliases = ['app:dispatch-wallet-deposit-polls'];

    protected $description = 'Dispatch a polling job for each open wallet deposit invoice.';

    public function handle(): int
    {
        $dispatched = 0;

        WalletDepositInvoice::query()
            ->openForPolling()
            ->chunkById(500, function (Collection $invoices) use (&$dispatched): void {
                foreach ($invoices as $invoice) {
                    PollWalletDepositInvoiceJob::dispatch($invoice->id);
                    $dispatched++;
                }
            });

        $this->comment("Dispatched {$dispatched} wallet deposit polling job(s).");

        return self::SUCCESS;
    }
}
