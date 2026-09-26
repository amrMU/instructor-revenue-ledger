<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Jobs\ProcessPayoutJob;
use App\Models\Payout;
use App\Repositories\InstructorLedgerRepository;
use App\Repositories\PayoutRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePayoutService
{
    public function __construct(
        private readonly PayoutRepository $payouts,
        private readonly InstructorLedgerRepository $ledger,
    ) {}

    public function createForInstructor(int $instructorId, bool $dispatch = true): ?Payout
    {
        try {
            $payout = DB::transaction(function () use ($instructorId): ?Payout {
                // The instructor lock serializes payout snapshots even when no eligible row exists yet.
                $this->payouts->lockInstructor($instructorId);
                $entries = $this->ledger->lockEligibleForInstructor($instructorId);
                $amount = (int) $entries->sum('amount_minor');
                if ($entries->isEmpty() || $amount <= 0) {
                    return null;
                }

                $payout = $this->payouts->create([
                    'instructor_id' => $instructorId,
                    'amount_minor' => $amount,
                    'currency' => $entries->first()->currency,
                    'status' => PayoutStatus::Pending,
                    'idempotency_key' => (string) Str::uuid(),
                ]);

                foreach ($entries as $entry) {
                    $this->payouts->addItem([
                        'payout_id' => $payout->id,
                        'ledger_entry_id' => $entry->id,
                        'amount_minor' => $entry->amount_minor,
                    ]);
                }

                return $payout->fresh('items');
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($payout !== null && $dispatch) {
            ProcessPayoutJob::dispatch($payout->id)->afterCommit();
        }

        return $payout;
    }

    public function processEligible(int $batchSize): int
    {
        $created = 0;
        $afterInstructorId = 0;
        do {
            $ids = $this->ledger->eligibleInstructorIdsAfter($afterInstructorId, $batchSize);
            foreach ($ids as $id) {
                $afterInstructorId = $id;
                if ($this->createForInstructor($id) !== null) {
                    $created++;
                }
            }
        } while (count($ids) === $batchSize);

        return $created;
    }
}
