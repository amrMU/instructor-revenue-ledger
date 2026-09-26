<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPaymentFactory extends Factory
{
    protected $model = SubscriptionPayment::class;

    public function definition(): array
    {
        return ['subscription_id' => Subscription::factory(), 'amount_minor' => 10000, 'currency' => 'EGP', 'status' => SubscriptionPaymentStatus::Paid, 'paid_at' => now()];
    }
}
