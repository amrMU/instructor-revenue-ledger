<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ProviderTransactionStatus::class, 'attempt_count' => 'integer', 'request_payload' => 'array', 'response_payload' => 'array', 'attempted_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
