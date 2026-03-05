<?php

namespace App\Console\Commands;

use App\Models\SmsLog;
use App\Services\Sms\Parser;
use App\Utils\Transaction;
use Illuminate\Console\Command;

class UpdateSmsLogParsingResultCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-sms-log-parsing-result';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $parser = new Parser();

        SmsLog::query()
            ->chunk(500, function ($logs) use ($parser) {
                Transaction::run(function () use ($logs, $parser) {
                    $logs->each(function (SmsLog $log) use ($parser) {
                        $log->update([
                            'parsing_result' => $parser->parseRaw($log->message),
                        ]);
                    });
                });
            });
    }
}
