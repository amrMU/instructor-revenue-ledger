<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $start = now()->startOfDay();

        return ['student_id' => User::factory()->student(), 'plan_id' => Plan::factory(), 'starts_at' => $start, 'ends_at' => $start->copy()->addMonth(), 'status' => SubscriptionStatus::Active];
    }
}
