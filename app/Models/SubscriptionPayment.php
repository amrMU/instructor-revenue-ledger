<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'refunded_amount_minor' => 'integer', 'paid_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime', 'status' => SubscriptionPaymentStatus::class];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function earningPeriods(): HasMany
    {
        return $this->hasMany(EarningPeriod::class);
    }
}
