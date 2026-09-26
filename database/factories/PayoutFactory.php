<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return ['instructor_id' => User::factory()->instructor(), 'amount_minor' => 1000, 'currency' => 'EGP', 'status' => PayoutStatus::Pending, 'idempotency_key' => (string) Str::uuid()];
    }
}
