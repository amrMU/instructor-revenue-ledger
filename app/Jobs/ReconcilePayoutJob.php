<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ProcessPayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ReconcilePayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(public readonly int $payoutId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('payout:'.$this->payoutId))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(ProcessPayoutService $service): void
    {
        $service->reconcile($this->payoutId);
    }
}
