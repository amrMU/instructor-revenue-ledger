<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instructor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');

            $table->char('currency', 3)->default('EGP');

            /*
             * pending
             * processing
             * pending_confirmation
             * paid
             * failed
             */
            $table->string('status')->default('pending');

            /*
             * Stable identity for this logical payout.
             */
            $table->uuid('idempotency_key')->unique();

            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();

            $table->timestamps();

            $table->index(['instructor_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
