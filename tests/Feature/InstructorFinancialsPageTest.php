<?php

declare(strict_types=1);

use App\Models\InstructorLedgerEntry;
use App\Models\User;

it('renders the authenticated instructors balance and payout empty state', function () {
    $instructor = User::factory()->instructor()->create();
    InstructorLedgerEntry::factory()->create(['instructor_id' => $instructor->id, 'amount_minor' => 12345]);

    $this->actingAs($instructor)
        ->get('/instructor')
        ->assertSuccessful()
        ->assertSee($instructor->name)
        ->assertSee($instructor->email)
        ->assertSee('Total earned')
        ->assertSee('123.45 EGP')
        ->assertSee('No payouts have been created yet.');
});

it('does not allow a student to access the instructor panel', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get('/instructor')
        ->assertForbidden();
});
