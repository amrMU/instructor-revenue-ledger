<?php

declare(strict_types=1);

namespace App\Contracts;

use App\ValueObjects\ProviderResult;

interface PaymentProvider
{
    public function transfer(string $idempotencyKey, int $instructorId, int $amountMinor, string $currency): ProviderResult;

    public function status(string $idempotencyKey): ProviderResult;
}
