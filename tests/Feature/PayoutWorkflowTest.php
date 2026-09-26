<?php

declare(strict_types=1);

use App\Contracts\PaymentProvider;
use App\Enums\PayoutStatus;
use App\Enums\ProviderTransactionStatus;
use App\Jobs\ProcessPayoutJob;
use App\Jobs\ReconcilePayoutJob;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\User;
use App\Services\CreatePayoutService;
use App\Services\InstructorBalanceService;
use App\Services\ProcessPayoutService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Tests\Fakes\ControllablePaymentProvider;

function payoutFixture(int $amount = 5000): array
{
    $instructor = User::factory()->instructor()->create();
    $entry = InstructorLedgerEntry::factory()->create(['instructor_id' => $instructor->id, 'amount_minor' => $amount]);

    return [$instructor, $entry];
}

function bindFakeProvider(): ControllablePaymentProvider
{
    $fake = new ControllablePaymentProvider;
    app()->instance(PaymentProvider::class, $fake);

    return $fake;
}

it('creates one fixed payout snapshot across duplicate creation attempts', function () {
    Bus::fake();
    [$instructor] = payoutFixture(5000);
    $service = app(CreatePayoutService::class);
    $first = $service->createForInstructor($instructor->id);
    InstructorLedgerEntry::factory()->create(['instructor_id' => $instructor->id, 'amount_minor' => 2000]);
    $second = $service->createForInstructor($instructor->id);

    expect($first->amount_minor)->toBe(5000)
        ->and($second->amount_minor)->toBe(2000)
        ->and($first->fresh()->amount_minor)->toBe(5000)
        ->and(Payout::query()->count())->toBe(2);
});

it('database uniqueness prevents one earning from entering two payouts', function () {
    Bus::fake();
    [$instructor, $entry] = payoutFixture();
    $first = app(CreatePayoutService::class)->createForInstructor($instructor->id, false);
    $other = Payout::factory()->create(['instructor_id' => $instructor->id]);

    expect(fn () => PayoutItem::query()->create(['payout_id' => $other->id, 'ledger_entry_id' => $entry->id, 'amount_minor' => 5000]))->toThrow(QueryException::class);
    expect($first->items)->toHaveCount(1);
});

it('processes confirmed success once under duplicate jobs and reports paid balance', function () {
    Bus::fake();
    $provider = bindFakeProvider();
    [$instructor] = payoutFixture(5000);
    $payout = app(CreatePayoutService::class)->createForInstructor($instructor->id, false);

    (new ProcessPayoutJob($payout->id))->handle(app(ProcessPayoutService::class));
    (new ProcessPayoutJob($payout->id))->handle(app(ProcessPayoutService::class));

    $balance = app(InstructorBalanceService::class)->forInstructor($instructor->id);
    expect($provider->transferCalls)->toBe(1)
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and($balance->totalEarned)->toBe(5000)
        ->and($balance->totalPaid)->toBe(5000)
        ->and($balance->outstandingBalance)->toBe(0);
});

it('persists a confirmed permanent provider failure', function () {
    Bus::fake();
    $provider = bindFakeProvider();
    $provider->transferStatus = ProviderTransactionStatus::Failed;
    [$instructor] = payoutFixture();
    $payout = app(CreatePayoutService::class)->createForInstructor($instructor->id, false);

    (new ProcessPayoutJob($payout->id))->handle(app(ProcessPayoutService::class));

    expect($provider->transferCalls)->toBe(1)
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Failed)
        ->and($payout->providerTransaction->status)->toBe(ProviderTransactionStatus::Failed);

    $provider->transferStatus = ProviderTransactionStatus::Succeeded;
    (new ProcessPayoutJob($payout->id))->handle(app(ProcessPayoutService::class));

    expect($provider->transferCalls)->toBe(2)
        ->and($provider->keys[0])->toBe($provider->keys[1])
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and($payout->providerTransaction->fresh()->attempt_count)->toBe(2);
});

it('does not transfer again after a timeout and pays only after reconciliation confirms success', function () {
    Bus::fake();
    $provider = bindFakeProvider();
    $provider->timeoutAfterSuccess = true;
    [$instructor] = payoutFixture();
    $payout = app(CreatePayoutService::class)->createForInstructor($instructor->id, false);
    $process = app(ProcessPayoutService::class);

    (new ProcessPayoutJob($payout->id))->handle($process);
    (new ProcessPayoutJob($payout->id))->handle($process);
    expect($payout->fresh()->status)->toBe(PayoutStatus::PendingConfirmation)
        ->and($provider->transferCalls)->toBe(1);

    (new ReconcilePayoutJob($payout->id))->handle($process);
    (new ProcessPayoutJob($payout->id))->handle($process);

    expect($provider->transferCalls)->toBe(1)
        ->and($provider->statusCalls)->toBe(1)
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and($payout->providerTransaction->fresh()->attempt_count)->toBe(1)
        ->and($provider->keys[0])->toBe($provider->keys[1]);
});

it('reconciliation can confirm failure without a second transfer', function () {
    Bus::fake();
    $provider = bindFakeProvider();
    $provider->timeoutAfterSuccess = true;
    $provider->lookupStatus = ProviderTransactionStatus::Failed;
    [$instructor] = payoutFixture();
    $payout = app(CreatePayoutService::class)->createForInstructor($instructor->id, false);
    $service = app(ProcessPayoutService::class);

    $service->process($payout->id);
    $service->reconcile($payout->id);

    expect($provider->transferCalls)->toBe(1)->and($payout->fresh()->status)->toBe(PayoutStatus::Failed);
});

it('a worker retry after an interrupted provider call reconciles instead of resubmitting money', function () {
    Bus::fake();
    $provider = bindFakeProvider();
    $provider->crashAfterSubmission = true;
    [$instructor] = payoutFixture();
    $payout = app(CreatePayoutService::class)->createForInstructor($instructor->id, false);
    $service = app(ProcessPayoutService::class);

    expect(fn () => $service->process($payout->id))->toThrow(RuntimeException::class);
    expect($payout->fresh()->status)->toBe(PayoutStatus::Processing);

    $provider->crashAfterSubmission = false;
    $service->process($payout->id);
    $service->reconcile($payout->id);

    expect($provider->transferCalls)->toBe(1)
        ->and($provider->statusCalls)->toBe(1)
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Paid);
});

it('running the payout command twice reserves each earning only once', function () {
    Bus::fake();
    payoutFixture(5000);
    $this->artisan('payouts:process', ['--batch' => 1])->assertSuccessful();
    $this->artisan('payouts:process', ['--batch' => 1])->assertSuccessful();

    expect(Payout::query()->count())->toBe(1)->and(PayoutItem::query()->count())->toBe(1);
});
