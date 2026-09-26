<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\PaymentProvider;
use App\Enums\ProviderTransactionStatus;
use App\Exceptions\ProviderTimeoutException;
use App\ValueObjects\ProviderResult;
use RuntimeException;

class ControllablePaymentProvider implements PaymentProvider
{
    public int $transferCalls = 0;

    public int $statusCalls = 0;

    public ProviderTransactionStatus $transferStatus = ProviderTransactionStatus::Succeeded;

    public ProviderTransactionStatus $lookupStatus = ProviderTransactionStatus::Succeeded;

    public bool $timeoutAfterSuccess = false;

    public bool $crashAfterSubmission = false;

    /** @var list<string> */
    public array $keys = [];

    public function transfer(string $idempotencyKey, int $instructorId, int $amountMinor, string $currency): ProviderResult
    {
        $this->transferCalls++;
        $this->keys[] = $idempotencyKey;
        if ($this->crashAfterSubmission) {
            throw new RuntimeException('Worker crashed after provider submission.');
        }
        if ($this->timeoutAfterSuccess) {
            throw new ProviderTimeoutException('Timed out after successful transfer.');
        }

        return new ProviderResult($this->transferStatus, 'fake-reference', ['amount_minor' => $amountMinor]);
    }

    public function status(string $idempotencyKey): ProviderResult
    {
        $this->statusCalls++;
        $this->keys[] = $idempotencyKey;

        return new ProviderResult($this->lookupStatus, 'fake-reference', ['reconciled' => true]);
    }
}
