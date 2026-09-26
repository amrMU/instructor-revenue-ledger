<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\InstructorLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstructorLedgerEntryFactory extends Factory
{
    protected $model = InstructorLedgerEntry::class;

    public function definition(): array
    {
        return ['instructor_id' => User::factory()->instructor(), 'type' => LedgerEntryType::Earning, 'amount_minor' => 1000, 'currency' => 'EGP', 'description' => 'Factory earning', 'occurred_at' => now()];
    }
}
