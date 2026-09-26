<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstructorLedgerRepository;
use App\ValueObjects\InstructorBalance;

class InstructorBalanceService
{
    public function __construct(private readonly InstructorLedgerRepository $ledger) {}

    public function forInstructor(int $instructorId): InstructorBalance
    {
        $totals = $this->ledger->totalsForInstructor($instructorId);

        return new InstructorBalance($totals['total_earned'], $totals['total_paid'], $totals['outstanding_balance']);
    }
}
