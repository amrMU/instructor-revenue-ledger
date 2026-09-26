<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CreatePayoutService;
use Illuminate\Console\Command;

class ProcessPayoutsCommand extends Command
{
    protected $signature = 'payouts:process {--batch= : Maximum instructors read per batch}';

    protected $description = 'Create fixed payout snapshots and dispatch their processing jobs';

    public function handle(CreatePayoutService $service): int
    {
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('ledger.payout_batch_size'))));
        $count = $service->processEligible($batchSize);
        $this->info("Created and dispatched {$count} payout(s).");

        return self::SUCCESS;
    }
}
