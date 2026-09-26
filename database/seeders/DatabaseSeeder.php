<?php

namespace Database\Seeders;

use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\CompleteEarningPeriodService;
use App\Services\GenerateEarningPeriodsService;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $student = User::factory()->student()->create(['name' => 'Ahmed Hassan', 'email' => 'ahmed.hassan@example.com']);
        $instructors = collect([
            User::factory()->instructor()->create(['name' => 'Omar Khaled', 'email' => 'omar.khaled@example.com']),
            User::factory()->instructor()->create(['name' => 'Mariam Adel', 'email' => 'mariam.adel@example.com']),
        ]);
        $plan = Plan::query()->create(['name' => 'Quarterly', 'duration_months' => 3, 'price_minor' => 30000, 'currency' => 'EGP', 'is_active' => true]);
        $start = now()->startOfMonth()->subMonths(2);
        $subscription = Subscription::query()->create(['student_id' => $student->id, 'plan_id' => $plan->id, 'starts_at' => $start, 'ends_at' => $start->copy()->addMonths(3), 'status' => SubscriptionStatus::Active]);
        $payment = SubscriptionPayment::query()->create(['subscription_id' => $subscription->id, 'amount_minor' => 30000, 'currency' => 'EGP', 'status' => SubscriptionPaymentStatus::Paid, 'paid_at' => $start]);
        $periods = app(GenerateEarningPeriodsService::class)->generate($payment->id);
        $periods->take(2)->each(fn ($period) => app(CompleteEarningPeriodService::class)->complete($period->id, $instructors->pluck('id')->all(), 6000));
    }
}
