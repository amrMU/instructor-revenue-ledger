<?php

declare(strict_types=1);

use App\Enums\EarningPeriodStatus;
use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\InstructorLedgerEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\CompleteEarningPeriodService;
use App\Services\GenerateEarningPeriodsService;
use App\Services\InstructorBalanceService;
use App\Services\RecordFinancialCorrectionService;
use App\Services\RefundSubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

function financialSubscription(int $months, int $amount, string $start = '2026-01-31 00:00:00'): array
{
    $student = User::factory()->student()->create();
    $plan = Plan::factory()->create(['duration_months' => $months, 'price_minor' => $amount]);
    $startsAt = CarbonImmutable::parse($start);
    $subscription = Subscription::factory()->create([
        'student_id' => $student->id,
        'plan_id' => $plan->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMonthsNoOverflow($months),
        'status' => SubscriptionStatus::Active,
    ]);
    $payment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'amount_minor' => $amount,
        'status' => SubscriptionPaymentStatus::Paid,
        'paid_at' => $startsAt,
    ]);

    return [$subscription, $payment];
}

it('generates start-anchored monthly periods and assigns duration remainder to the final period', function () {
    [, $payment] = financialSubscription(3, 10001);
    $periods = app(GenerateEarningPeriodsService::class)->generate($payment->id);

    expect($periods)->toHaveCount(3)
        ->and($periods->pluck('gross_amount_minor')->all())->toBe([3333, 3333, 3335])
        ->and($periods[0]->period_start->toDateString())->toBe('2026-01-31')
        ->and($periods[0]->period_end->toDateString())->toBe('2026-02-28')
        ->and($periods[1]->period_end->toDateString())->toBe('2026-03-31')
        ->and($periods->sum('gross_amount_minor'))->toBe(10001);
});

it('allocates exact revenue and records an indivisible instructor remainder for the platform', function () {
    [, $payment] = financialSubscription(1, 10001, '2026-01-01');
    $period = app(GenerateEarningPeriodsService::class)->generate($payment->id)->first();
    $instructors = User::factory()->count(3)->instructor()->create();

    $completed = app(CompleteEarningPeriodService::class)->complete($period->id, $instructors->modelKeys(), 5000);

    expect($completed->instructor_pool_minor)->toBe(4998)
        ->and($completed->platform_amount_minor)->toBe(5001)
        ->and($completed->rounding_adjustment_minor)->toBe(2)
        ->and($completed->ledgerEntries->pluck('amount_minor')->all())->toBe([1666, 1666, 1666])
        ->and($completed->platform_amount_minor + $completed->instructor_pool_minor + $completed->rounding_adjustment_minor)->toBe(10001);
});

it('snapshots share and instructor set per period without recalculating completed history', function () {
    [, $payment] = financialSubscription(3, 30000, '2026-01-01');
    $periods = app(GenerateEarningPeriodsService::class)->generate($payment->id);
    $firstInstructor = User::factory()->instructor()->create();
    $secondInstructor = User::factory()->instructor()->create();
    $service = app(CompleteEarningPeriodService::class);

    $first = $service->complete($periods[0]->id, [$firstInstructor->id], 5000);
    $second = $service->complete($periods[1]->id, [$firstInstructor->id, $secondInstructor->id], 6000);
    $again = $service->complete($periods[0]->id, [$secondInstructor->id], 9000);

    expect($first->instructor_share_basis_points)->toBe(5000)
        ->and($first->ledgerEntries->pluck('instructor_id')->all())->toBe([$firstInstructor->id])
        ->and($second->instructor_share_basis_points)->toBe(6000)
        ->and($second->ledgerEntries->pluck('instructor_id')->sort()->values()->all())->toBe([$firstInstructor->id, $secondInstructor->id])
        ->and($again->instructor_share_basis_points)->toBe(5000)
        ->and(InstructorLedgerEntry::query()->where('earning_period_id', $first->id)->count())->toBe(1);
});

it('stops all future earnings on refund and appends a correction without rewriting history', function () {
    [$subscription, $payment] = financialSubscription(3, 30000, '2026-01-01');
    $periods = app(GenerateEarningPeriodsService::class)->generate($payment->id);
    $instructor = User::factory()->instructor()->create();
    app(CompleteEarningPeriodService::class)->complete($periods[0]->id, [$instructor->id], 6000);

    $refund = app(RefundSubscriptionService::class)->refund(
        $subscription->id,
        CarbonImmutable::parse('2026-02-15'),
        [$instructor->id => -500],
    );

    expect($refund)->toBeGreaterThan(0)
        ->and($periods[0]->fresh()->status)->toBe(EarningPeriodStatus::Completed)
        ->and($periods[1]->fresh()->status)->toBe(EarningPeriodStatus::Cancelled)
        ->and($periods[2]->fresh()->status)->toBe(EarningPeriodStatus::Cancelled)
        ->and(InstructorLedgerEntry::query()->where('type', LedgerEntryType::Earning)->value('amount_minor'))->toBe(6000)
        ->and(InstructorLedgerEntry::query()->where('type', LedgerEntryType::RefundAdjustment)->value('amount_minor'))->toBe(-500)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Refunded);

    $sameRefund = app(RefundSubscriptionService::class)->refund($subscription->id, CarbonImmutable::parse('2026-02-15'), [$instructor->id => -500]);
    expect($sameRefund)->toBe($refund)
        ->and(InstructorLedgerEntry::query()->where('type', LedgerEntryType::RefundAdjustment)->count())->toBe(1);
});

it('derives earned paid and outstanding totals from immutable records', function () {
    $instructor = User::factory()->instructor()->create();
    InstructorLedgerEntry::factory()->create(['instructor_id' => $instructor->id, 'amount_minor' => 7000]);
    InstructorLedgerEntry::factory()->create(['instructor_id' => $instructor->id, 'type' => LedgerEntryType::Correction, 'amount_minor' => -500, 'reference_type' => 'test', 'reference_id' => 1]);

    $balance = app(InstructorBalanceService::class)->forInstructor($instructor->id);
    expect($balance->totalEarned)->toBe(6500)->and($balance->totalPaid)->toBe(0)->and($balance->outstandingBalance)->toBe(6500);
});

it('records a referenced financial correction once under retry', function () {
    $instructor = User::factory()->instructor()->create();
    $service = app(RecordFinancialCorrectionService::class);
    $first = $service->record($instructor->id, 250, 'review_correction', 99, 'Approved correction', CarbonImmutable::parse('2026-01-10'));
    $again = $service->record($instructor->id, 250, 'review_correction', 99, 'Approved correction', CarbonImmutable::parse('2026-01-10'));

    expect($again->id)->toBe($first->id)
        ->and(InstructorLedgerEntry::query()->where('type', LedgerEntryType::Correction)->count())->toBe(1);
});

it('enforces confirmed ledger and period immutability in the database', function () {
    [, $payment] = financialSubscription(1, 10000, '2026-01-01');
    $period = app(GenerateEarningPeriodsService::class)->generate($payment->id)->first();
    $instructor = User::factory()->instructor()->create();
    $period = app(CompleteEarningPeriodService::class)->complete($period->id, [$instructor->id], 6000);
    $entry = $period->ledgerEntries->first();

    expect(fn () => InstructorLedgerEntry::query()->whereKey($entry->id)->update(['amount_minor' => 1]))->toThrow(QueryException::class)
        ->and(fn () => $period->update(['instructor_pool_minor' => 1]))->toThrow(QueryException::class);
});
