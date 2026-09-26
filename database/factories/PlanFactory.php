<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $months = fake()->randomElement([1, 3, 12]);

        return ['name' => "{$months}-month plan", 'duration_months' => $months, 'price_minor' => $months * 10000, 'currency' => 'EGP', 'is_active' => true];
    }
}
