<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payout_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Stable idempotency key used with the external provider.
             */
            $table->uuid('idempotency_key');

            $table->string('provider_reference')->nullable();

            /*
             * initiated
             * succeeded
             * failed
             * unknown
             */
            $table->string('status')->default('initiated');
            $table->unsignedInteger('attempt_count')->default(0);

            /*
             * Useful for the mocked provider and debugging the challenge.
             * Avoid storing secrets/sensitive credentials here.
             */
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['payout_id', 'idempotency_key'],
                'provider_transaction_idempotency_unique'
            );
            $table->unique('payout_id');
            $table->unique('idempotency_key');

            $table->index(['payout_id', 'status']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_transactions');
    }
};
