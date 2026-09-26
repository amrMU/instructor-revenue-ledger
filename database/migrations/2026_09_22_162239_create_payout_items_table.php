<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payout_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('ledger_entry_id')
                ->constrained('instructor_ledger_entries')
                ->restrictOnDelete();

            /*
             * Snapshot of the amount included from this ledger entry.
             * Currently every ledger entry is reserved in full.
             */
            $table->bigInteger('amount_minor');

            $table->timestamps();

            /*
             * A financial earning/adjustment can belong to only one payout.
             */
            $table->unique('ledger_entry_id');

            $table->unique(
                ['payout_id', 'ledger_entry_id'],
                'payout_item_unique'
            );

            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
    }
};
