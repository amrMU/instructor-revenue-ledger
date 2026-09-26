<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('instructor_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             * earning
             * refund_adjustment
             * correction
             */
            $table->string('type');

            /*
             * Signed amount:
             *
             * earning            => positive
             * refund adjustment  => negative
             * correction         => positive or negative
             *
             * Do NOT use unsignedBigInteger here.
             */
            $table->bigInteger('amount_minor');

            $table->char('currency', 3)->default('EGP');

            $table->foreignId('earning_period_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            /*
             * Generic business source for adjustments/corrections.
             */
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('description')->nullable();
            $table->dateTime('occurred_at');

            $table->timestamps();

            /*
             * Prevent duplicate earning allocation for the same instructor
             * in the same earning period.
             */
            $table->unique(
                ['earning_period_id', 'instructor_id', 'type'],
                'ledger_period_instructor_type_unique'
            );

            $table->index(['instructor_id', 'type']);
            $table->index(['instructor_id', 'created_at']);
            $table->index(['instructor_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->unique(
                ['instructor_id', 'type', 'reference_type', 'reference_id'],
                'ledger_adjustment_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_ledger_entries');
    }
};
