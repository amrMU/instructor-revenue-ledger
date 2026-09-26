<?php

declare(strict_types=1);

namespace App\ValueObjects;

final readonly class InstructorBalance
{
    public function __construct(
        public int $totalEarned,
        public int $totalPaid,
        public int $outstandingBalance,
    ) {}
}
