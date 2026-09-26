<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\PayoutStatus;
use App\Models\InstructorLedgerEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InstructorLedgerRepository
{
    public function create(array $attributes): InstructorLedgerEntry
    {
        return InstructorLedgerEntry::query()->create($attributes);
    }

    public function firstOrCreateByReference(array $identity, array $attributes): InstructorLedgerEntry
    {
        return InstructorLedgerEntry::query()->firstOrCreate($identity, $attributes);
    }

    /** @return Collection<int, InstructorLedgerEntry> */
    public function lockEligibleForInstructor(int $instructorId): Collection
    {
        return InstructorLedgerEntry::query()
            ->select('instructor_ledger_entries.*')
            ->leftJoin('payout_items', 'payout_items.ledger_entry_id', '=', 'instructor_ledger_entries.id')
            ->where('instructor_ledger_entries.instructor_id', $instructorId)
            ->whereNull('payout_items.id')
            ->orderBy('instructor_ledger_entries.id')
            ->lockForUpdate()
            ->get();
    }

    /** @return list<int> */
    public function eligibleInstructorIdsAfter(int $afterId, int $limit): array
    {
        return InstructorLedgerEntry::query()
            ->select('instructor_ledger_entries.instructor_id')
            ->leftJoin('payout_items', 'payout_items.ledger_entry_id', '=', 'instructor_ledger_entries.id')
            ->whereNull('payout_items.id')
            ->where('instructor_ledger_entries.instructor_id', '>', $afterId)
            ->groupBy('instructor_ledger_entries.instructor_id')
            ->orderBy('instructor_ledger_entries.instructor_id')
            ->limit($limit)
            ->pluck('instructor_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array{total_earned: int, total_paid: int, outstanding_balance: int} */
    public function totalsForInstructor(int $instructorId): array
    {
        $earned = (int) InstructorLedgerEntry::query()->where('instructor_id', $instructorId)->sum('amount_minor');
        $paid = (int) DB::table('payout_items')
            ->join('payouts', 'payouts.id', '=', 'payout_items.payout_id')
            ->where('payouts.instructor_id', $instructorId)
            ->where('payouts.status', PayoutStatus::Paid->value)
            ->sum('payout_items.amount_minor');

        return ['total_earned' => $earned, 'total_paid' => $paid, 'outstanding_balance' => $earned - $paid];
    }
}
