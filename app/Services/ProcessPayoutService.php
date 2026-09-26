<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Enums\PayoutStatus;
use App\Enums\ProviderTransactionStatus;
use App\Exceptions\ProviderTimeoutException;
use App\Jobs\ReconcilePayoutJob;
use App\Repositories\PayoutRepository;
use App\Repositories\ProviderTransactionRepository;
use App\ValueObjects\ProviderResult;
use Illuminate\Support\Facades\DB;

class ProcessPayoutService
{
    public function __construct(
        private readonly PayoutRepository $payouts,
        private readonly ProviderTransactionRepository $transactions,
        private readonly PaymentProvider $provider,
    ) {}

    public function process(int $payoutId): void
    {
        $payout = DB::transaction(function () use ($payoutId) {
            $payout = $this->payouts->lock($payoutId);
            if (! $payout->status->canBeginProcessing()) {
                return null;
            }

            $payout->update(['status' => PayoutStatus::Processing, 'failed_at' => null]);
            $transaction = $this->transactions->firstOrCreateForPayout(
                $payout->id,
                $payout->idempotency_key,
                ['status' => ProviderTransactionStatus::Initiated],
            );
            $transaction->update([
                'status' => ProviderTransactionStatus::Initiated,
                'attempt_count' => $transaction->attempt_count + 1,
                'request_payload' => ['amount_minor' => $payout->amount_minor, 'currency' => $payout->currency],
                'response_payload' => null,
                'attempted_at' => now(),
                'confirmed_at' => null,
            ]);

            return $payout->fresh();
        }, 3);

        if ($payout === null) {
            return;
        }

        try {
            $result = $this->provider->transfer(
                $payout->idempotency_key,
                $payout->instructor_id,
                $payout->amount_minor,
                $payout->currency,
            );
            $this->persistConfirmedResult($payout->id, $result);
        } catch (ProviderTimeoutException $exception) {
            DB::transaction(function () use ($payout, $exception): void {
                $locked = $this->payouts->lock($payout->id);
                if ($locked->status !== PayoutStatus::Processing) {
                    return;
                }
                $transaction = $this->transactions->lockForPayout($payout->id);
                $transaction->update([
                    'status' => ProviderTransactionStatus::Unknown,
                    'response_payload' => ['message' => $exception->getMessage()],
                ]);
                $locked->update(['status' => PayoutStatus::PendingConfirmation]);
            }, 3);
            ReconcilePayoutJob::dispatch($payout->id)->delay(now()->addSeconds(5));
        }
    }

    public function reconcile(int $payoutId): void
    {
        $payout = $this->payouts->find($payoutId);
        if ($payout === null || ! in_array($payout->status, [PayoutStatus::Processing, PayoutStatus::PendingConfirmation], true)) {
            return;
        }

        $result = $this->provider->status($payout->idempotency_key);
        $this->persistConfirmedResult($payout->id, $result);
    }

    public function dispatchReconciliationBatch(int $batchSize): int
    {
        $count = 0;
        $afterId = 0;
        do {
            $ids = $this->payouts->reconciliationIds($afterId, $batchSize);
            foreach ($ids as $id) {
                $afterId = $id;
                ReconcilePayoutJob::dispatch($id);
                $count++;
            }
        } while (count($ids) === $batchSize);

        return $count;
    }

    private function persistConfirmedResult(int $payoutId, ProviderResult $result): void
    {
        DB::transaction(function () use ($payoutId, $result): void {
            $payout = $this->payouts->lock($payoutId);
            if ($payout->status === PayoutStatus::Paid) {
                return;
            }
            if (! in_array($payout->status, [PayoutStatus::Processing, PayoutStatus::PendingConfirmation], true)) {
                return;
            }

            $transaction = $this->transactions->lockForPayout($payoutId);
            if ($result->status === ProviderTransactionStatus::Succeeded) {
                $transaction->update([
                    'status' => ProviderTransactionStatus::Succeeded,
                    'provider_reference' => $result->providerReference,
                    'response_payload' => $result->payload,
                    'confirmed_at' => now(),
                ]);
                $payout->update(['status' => PayoutStatus::Paid, 'paid_at' => now(), 'failed_at' => null]);

                return;
            }

            if ($result->status === ProviderTransactionStatus::Failed) {
                $transaction->update([
                    'status' => ProviderTransactionStatus::Failed,
                    'provider_reference' => $result->providerReference,
                    'response_payload' => $result->payload,
                    'confirmed_at' => now(),
                ]);
                $payout->update(['status' => PayoutStatus::Failed, 'failed_at' => now()]);
            }
        }, 3);
    }
}
