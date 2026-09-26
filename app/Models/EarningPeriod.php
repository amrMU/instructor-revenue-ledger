<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EarningPeriodStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EarningPeriod extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_datetime', 'period_end' => 'immutable_datetime', 'closed_at' => 'immutable_datetime', 'status' => EarningPeriodStatus::class, 'gross_amount_minor' => 'integer', 'platform_amount_minor' => 'integer', 'instructor_pool_minor' => 'integer', 'rounding_adjustment_minor' => 'integer', 'instructor_share_basis_points' => 'integer'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InstructorLedgerEntry::class);
    }
}
