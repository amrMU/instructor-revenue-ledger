<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('subscription_payment_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('period_start');
            $table->date('period_end');

            /*
             * Example:
             * 30.00 means platform receives 30%
             *
             * We are not using this column for money calculations directly
             * without converting to integer-safe arithmetic in application code.
             */
            $table->unsignedSmallInteger('instructor_share_basis_points')->nullable();

            /*
             * Portion of the subscription payment attributed to this earning period.
             */
            $table->unsignedBigInteger('gross_amount_minor');

            $table->unsignedBigInteger('platform_amount_minor');

            /*
             * Amount distributed equally to instructors after rounding.
             */
            $table->unsignedBigInteger('instructor_pool_minor');

            /*
             * Minor-unit remainder assigned to the platform because
             * the instructors' amount could not be divided evenly.
             */
            $table->unsignedBigInteger('rounding_adjustment_minor')
                ->default(0);

            $table->string('status')->default('open');

            $table->dateTime('closed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'period_start', 'period_end'],
                'earning_period_unique'
            );

            $table->index(['status', 'period_end']);
            $table->index(['subscription_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_periods');
    }
};
