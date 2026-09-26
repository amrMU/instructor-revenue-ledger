<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->unsignedSmallInteger('duration_months');

            // Money stored in minor units (piastres for EGP)
            $table->unsignedBigInteger('price_minor');

            $table->char('currency', 3)->default('EGP');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'duration_months']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};