<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EarningPeriodStatus;
use App\Models\EarningPeriod;
use App\Repositories\EarningPeriodRepository;
use App\Repositories\SubscriptionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateEarningPeriodsService
{
    public function __construct(
        private readonly EarningPeriodRepository $periods,
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    /** @return Collection<int, EarningPeriod> */
    public function generate(int $paymentId): Collection
    {
        return DB::transaction(function () use ($paymentId): Collection {
            $payment = $this->subscriptions->lockPaymentWithSubscription($paymentId);
            $subscription = $payment->subscription;
            $months = (int) $subscription->plan->duration_months;
            if (! in_array($months, [1, 3, 12], true)) {
                throw new InvalidArgumentException('A plan duration must be 1, 3, or 12 months.');
            }

            $existing = $this->periods->forPayment($paymentId);
            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $baseAmount = intdiv($payment->amount_minor, $months);
            $durationRemainder = $payment->amount_minor % $months;
            $start = CarbonImmutable::instance($subscription->starts_at);

            for ($index = 0; $index < $months; $index++) {
                $periodStart = $start->addMonthsNoOverflow($index);
                $periodEnd = $index === $months - 1
                    ? CarbonImmutable::instance($subscription->ends_at)
                    : $start->addMonthsNoOverflow($index + 1);

                $this->periods->create([
                    'subscription_id' => $subscription->id,
                    'subscription_payment_id' => $payment->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'gross_amount_minor' => $baseAmount + ($index === $months - 1 ? $durationRemainder : 0),
                    'platform_amount_minor' => 0,
                    'instructor_pool_minor' => 0,
                    'rounding_adjustment_minor' => 0,
                    'status' => EarningPeriodStatus::Open,
                ]);
            }

            return $this->periods->forPayment($paymentId);
        }, 3);
    }
}
