<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EarningPeriodStatus;
use App\Enums\LedgerEntryType;
use App\Models\EarningPeriod;
use App\Repositories\EarningPeriodRepository;
use App\Repositories\InstructorLedgerRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class CompleteEarningPeriodService
{
    public function __construct(
        private readonly EarningPeriodRepository $periods,
        private readonly InstructorLedgerRepository $ledger,
        private readonly UserRepository $users,
    ) {}

    /** @param list<int> $instructorIds */
    public function complete(int $periodId, array $instructorIds, int $instructorShareBasisPoints): EarningPeriod
    {
        $instructorIds = array_values(array_unique(array_map('intval', $instructorIds)));
        if ($instructorIds === [] || $instructorShareBasisPoints < 0 || $instructorShareBasisPoints > 10000) {
            throw new InvalidArgumentException('Instructors and a share from 0 through 10,000 basis points are required.');
        }

        return DB::transaction(function () use ($periodId, $instructorIds, $instructorShareBasisPoints): EarningPeriod {
            $period = $this->periods->lock($periodId);
            if ($period->status === EarningPeriodStatus::Completed) {
                return $period->fresh();
            }
            if ($period->status !== EarningPeriodStatus::Open) {
                throw new LogicException('Only an open earning period can be completed.');
            }
            if ($period->period_end->isFuture()) {
                throw new LogicException('An earning period cannot be completed before it ends.');
            }

            $validCount = $this->users->countInstructors($instructorIds);
            if ($validCount !== count($instructorIds)) {
                throw new InvalidArgumentException('Every allocation recipient must be an instructor.');
            }

            $rawInstructorPool = intdiv($period->gross_amount_minor * $instructorShareBasisPoints, 10000);
            $perInstructor = intdiv($rawInstructorPool, count($instructorIds));
            $roundingAdjustment = $rawInstructorPool % count($instructorIds);
            $distributedPool = $perInstructor * count($instructorIds);

            if ($perInstructor > 0) {
                foreach ($instructorIds as $instructorId) {
                    $this->ledger->create([
                        'instructor_id' => $instructorId,
                        'type' => LedgerEntryType::Earning,
                        'amount_minor' => $perInstructor,
                        'currency' => $period->payment->currency,
                        'earning_period_id' => $period->id,
                        'description' => 'Instructor revenue for earning period '.$period->id,
                        'occurred_at' => $period->period_end,
                    ]);
                }
            }

            $period->update([
                'instructor_share_basis_points' => $instructorShareBasisPoints,
                'platform_amount_minor' => $period->gross_amount_minor - $rawInstructorPool,
                'instructor_pool_minor' => $distributedPool,
                'rounding_adjustment_minor' => $roundingAdjustment,
                'status' => EarningPeriodStatus::Completed,
                'closed_at' => now(),
            ]);

            return $period->fresh('ledgerEntries');
        }, 3);
    }
}
