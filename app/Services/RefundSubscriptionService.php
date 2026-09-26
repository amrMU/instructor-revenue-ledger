<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Repositories\EarningPeriodRepository;
use App\Repositories\InstructorLedgerRepository;
use App\Repositories\SubscriptionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundSubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly EarningPeriodRepository $periods,
        private readonly InstructorLedgerRepository $ledger,
    ) {}

    /**
     * @param  array<int, int>  $corrections  Signed correction amount keyed by instructor id.
     */
    public function refund(int $subscriptionId, CarbonImmutable $effectiveAt, array $corrections = []): int
    {
        return DB::transaction(function () use ($subscriptionId, $effectiveAt, $corrections): int {
            $subscription = $this->subscriptions->lockWithPayment($subscriptionId);
            $payment = $subscription->payments->firstOrFail();
            if ($subscription->status === SubscriptionStatus::Refunded) {
                return $payment->refunded_amount_minor;
            }
            $start = CarbonImmutable::instance($subscription->starts_at);
            $end = CarbonImmutable::instance($subscription->ends_at);
            if ($effectiveAt->lessThan($start) || $effectiveAt->greaterThan($end)) {
                throw new InvalidArgumentException('The refund date must fall within the subscription term.');
            }

            $totalSeconds = (int) $start->diffInSeconds($end);
            $unusedSeconds = (int) $effectiveAt->diffInSeconds($end);
            $refundAmount = intdiv($payment->amount_minor * $unusedSeconds, $totalSeconds);

            $subscription->update(['status' => SubscriptionStatus::Refunded, 'refunded_at' => $effectiveAt]);
            $payment->update([
                'status' => $refundAmount === $payment->amount_minor ? SubscriptionPaymentStatus::Refunded : SubscriptionPaymentStatus::PartiallyRefunded,
                'refunded_amount_minor' => $refundAmount,
                'refunded_at' => $effectiveAt,
            ]);
            $this->periods->cancelOpenForSubscription($subscription->id);

            foreach ($corrections as $instructorId => $amountMinor) {
                if ($amountMinor >= 0) {
                    throw new InvalidArgumentException('Refund corrections must be negative.');
                }
                $this->ledger->create([
                    'instructor_id' => (int) $instructorId,
                    'type' => LedgerEntryType::RefundAdjustment,
                    'amount_minor' => $amountMinor,
                    'currency' => $payment->currency,
                    'reference_type' => 'subscription_refund',
                    'reference_id' => $payment->id,
                    'description' => 'Refund correction for subscription '.$subscription->id,
                    'occurred_at' => $effectiveAt,
                ]);
            }

            return $refundAmount;
        }, 3);
    }
}
