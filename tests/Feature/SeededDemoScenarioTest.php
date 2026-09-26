<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\InstructorBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds deterministic demo identities and exact initial financial values', function () {
    $this->seed(DatabaseSeeder::class);

    $student = User::query()->where('email', 'ahmed.hassan@example.com')->firstOrFail();
    $omar = User::query()->where('email', 'omar.khaled@example.com')->firstOrFail();
    $mariam = User::query()->where('email', 'mariam.adel@example.com')->firstOrFail();

    expect($student->name)->toBe('Ahmed Hassan')
        ->and($student->role)->toBe(UserRole::Student)
        ->and($omar->name)->toBe('Omar Khaled')
        ->and($omar->role)->toBe(UserRole::Instructor)
        ->and($mariam->name)->toBe('Mariam Adel')
        ->and($mariam->role)->toBe(UserRole::Instructor)
        ->and(Hash::check('password', $student->password))->toBeTrue()
        ->and(Hash::check('password', $omar->password))->toBeTrue()
        ->and(Hash::check('password', $mariam->password))->toBeTrue()
        ->and(Plan::query()->sole()->duration_months)->toBe(3)
        ->and(SubscriptionPayment::query()->sole()->amount_minor)->toBe(30000);

    foreach ([$omar, $mariam] as $instructor) {
        $balance = app(InstructorBalanceService::class)->forInstructor($instructor->id);
        expect($balance->totalEarned)->toBe(6000)
            ->and($balance->totalPaid)->toBe(0)
            ->and($balance->outstandingBalance)->toBe(6000);
    }
});
