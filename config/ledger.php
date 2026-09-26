<?php

use App\Enums\ProviderOutcome;

return [
    'mock_provider_outcome' => env('MOCK_PROVIDER_OUTCOME', ProviderOutcome::Success->value),
    'payout_batch_size' => (int) env('PAYOUT_BATCH_SIZE', 100),
];
