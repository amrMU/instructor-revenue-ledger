<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\InstructorLedgerEntry;
use App\Repositories\InstructorLedgerRepository;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class RecordFinancialCorrectionService
{
    public function __construct(private readonly InstructorLedgerRepository $ledger) {}

    public function record(
        int $instructorId,
        int $amountMinor,
        string $referenceType,
        int $referenceId,
        string $description,
        CarbonImmutable $occurredAt,
        string $currency = 'EGP',
    ): InstructorLedgerEntry {
        if ($amountMinor === 0 || $referenceType === '') {
            throw new InvalidArgumentException('A correction requires a non-zero amount and stable reference.');
        }

        return $this->ledger->firstOrCreateByReference(
            [
                'instructor_id' => $instructorId,
                'type' => LedgerEntryType::Correction,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
            [
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'description' => $description,
                'occurred_at' => $occurredAt,
            ],
        );
    }
}
