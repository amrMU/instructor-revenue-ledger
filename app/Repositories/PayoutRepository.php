<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PayoutRepository
{
    public function lockInstructor(int $instructorId): User
    {
        return User::query()->lockForUpdate()->findOrFail($instructorId);
    }

    public function create(array $attributes): Payout
    {
        return Payout::query()->create($attributes);
    }

    public function addItem(array $attributes): PayoutItem
    {
        return PayoutItem::query()->create($attributes);
    }

    public function lock(int $id): Payout
    {
        return Payout::query()->lockForUpdate()->findOrFail($id);
    }

    public function find(int $id): ?Payout
    {
        return Payout::query()->find($id);
    }

    /** @return Collection<int, Payout> */
    public function historyForInstructor(int $instructorId): Collection
    {
        return Payout::query()->where('instructor_id', $instructorId)->latest('id')->get();
    }

    /** @return list<int> */
    public function reconciliationIds(int $afterId, int $limit): array
    {
        return Payout::query()
            ->whereIn('status', [PayoutStatus::Processing, PayoutStatus::PendingConfirmation])
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
