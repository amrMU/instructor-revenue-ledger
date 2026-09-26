<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentProvider;
use App\Enums\ProviderOutcome;
use App\Enums\ProviderTransactionStatus;
use App\Exceptions\ProviderTimeoutException;
use App\ValueObjects\ProviderResult;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class MockPaymentProvider implements PaymentProvider
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function transfer(string $idempotencyKey, int $instructorId, int $amountMinor, string $currency): ProviderResult
    {
        $cacheKey = $this->cacheKey($idempotencyKey);
        $existing = $this->cache->get($cacheKey);
        if (is_array($existing)) {
            return $this->resultFromStored($existing);
        }

        $outcome = ProviderOutcome::from((string) config('ledger.mock_provider_outcome', ProviderOutcome::Success->value));
        $reference = 'mock_'.Str::lower(Str::random(20));

        if ($outcome === ProviderOutcome::PermanentFailure) {
            $stored = ['status' => ProviderTransactionStatus::Failed->value, 'reference' => $reference];
            $this->cache->forever($cacheKey, $stored);

            return $this->resultFromStored($stored);
        }

        $stored = ['status' => ProviderTransactionStatus::Succeeded->value, 'reference' => $reference];
        $this->cache->forever($cacheKey, $stored);

        if ($outcome === ProviderOutcome::TimeoutAfterSuccess) {
            throw new ProviderTimeoutException('The provider response timed out after transfer submission.');
        }

        return $this->resultFromStored($stored);
    }

    public function status(string $idempotencyKey): ProviderResult
    {
        $stored = $this->cache->get($this->cacheKey($idempotencyKey));
        if (! is_array($stored)) {
            return new ProviderResult(ProviderTransactionStatus::Failed, payload: ['reason' => 'operation_not_found']);
        }

        return $this->resultFromStored($stored);
    }

    private function resultFromStored(array $stored): ProviderResult
    {
        return new ProviderResult(
            ProviderTransactionStatus::from($stored['status']),
            $stored['reference'] ?? null,
            ['mock' => true],
        );
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'mock-provider:'.$idempotencyKey;
    }
}
