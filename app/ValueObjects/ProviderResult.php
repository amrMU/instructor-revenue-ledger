<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\ProviderTransactionStatus;

final readonly class ProviderResult
{
    public function __construct(
        public ProviderTransactionStatus $status,
        public ?string $providerReference = null,
        public array $payload = [],
    ) {}
}
