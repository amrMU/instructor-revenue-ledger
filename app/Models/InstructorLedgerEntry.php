<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstructorLedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => LedgerEntryType::class, 'amount_minor' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function earningPeriod(): BelongsTo
    {
        return $this->belongsTo(EarningPeriod::class);
    }

    public function payoutItem(): HasOne
    {
        return $this->hasOne(PayoutItem::class, 'ledger_entry_id');
    }
}
