<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProviderTransaction;

class ProviderTransactionRepository
{
    public function firstOrCreateForPayout(int $payoutId, string $idempotencyKey, array $attributes): ProviderTransaction
    {
        return ProviderTransaction::query()->firstOrCreate(
            ['payout_id' => $payoutId, 'idempotency_key' => $idempotencyKey],
            $attributes,
        );
    }

    public function lockForPayout(int $payoutId): ProviderTransaction
    {
        return ProviderTransaction::query()->where('payout_id', $payoutId)->lockForUpdate()->firstOrFail();
    }
}
