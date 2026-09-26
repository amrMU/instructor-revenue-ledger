<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('refunded_amount_minor')->default(0);

            $table->char('currency', 3)->default('EGP');

            $table->string('status')->default('paid');

            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();

            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
