<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\PayoutRepository;
use App\Repositories\UserRepository;
use App\ValueObjects\InstructorBalance;
use Illuminate\Database\Eloquent\Collection;

class InstructorFinancialSummaryService
{
    public function __construct(
        private readonly InstructorBalanceService $balances,
        private readonly PayoutRepository $payouts,
        private readonly UserRepository $users,
    ) {}

    /** @return array{instructor: User, balance: InstructorBalance, payouts: Collection} */
    public function forInstructor(int $instructorId): array
    {
        return [
            'instructor' => $this->users->findInstructor($instructorId),
            'balance' => $this->balances->forInstructor($instructorId),
            'payouts' => $this->payouts->historyForInstructor($instructorId),
        ];
    }
}
