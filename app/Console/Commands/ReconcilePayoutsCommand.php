<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProcessPayoutService;
use Illuminate\Console\Command;

class ReconcilePayoutsCommand extends Command
{
    protected $signature = 'payouts:reconcile {--batch=100}';

    protected $description = 'Dispatch bounded provider status checks for uncertain payout operations';

    public function handle(ProcessPayoutService $service): int
    {
        $batchSize = max(1, min(1000, (int) $this->option('batch')));
        $count = $service->dispatchReconciliationBatch($batchSize);
        $this->info("Dispatched {$count} reconciliation job(s).");

        return self::SUCCESS;
    }
}
