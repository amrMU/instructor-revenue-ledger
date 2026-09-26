<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\EarningPeriodStatus;
use App\Models\EarningPeriod;
use Illuminate\Support\Collection;

class EarningPeriodRepository
{
    public function create(array $attributes): EarningPeriod
    {
        return EarningPeriod::query()->create($attributes);
    }

    public function lock(int $id): EarningPeriod
    {
        return EarningPeriod::query()->lockForUpdate()->findOrFail($id);
    }

    public function cancelOpenForSubscription(int $subscriptionId): int
    {
        return EarningPeriod::query()
            ->where('subscription_id', $subscriptionId)
            ->where('status', EarningPeriodStatus::Open)
            ->update(['status' => EarningPeriodStatus::Cancelled]);
    }

    /** @return Collection<int, EarningPeriod> */
    public function forPayment(int $paymentId): Collection
    {
        return EarningPeriod::query()->where('subscription_payment_id', $paymentId)->orderBy('period_start')->get();
    }
}
