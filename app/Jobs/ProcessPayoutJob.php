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

class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $payoutId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('payout:'.$this->payoutId))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(ProcessPayoutService $service): void
    {
        $service->process($this->payoutId);
    }
}
