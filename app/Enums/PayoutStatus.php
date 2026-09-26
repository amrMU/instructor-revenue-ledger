<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case PendingConfirmation = 'pending_confirmation';
    case Paid = 'paid';
    case Failed = 'failed';

    public function canBeginProcessing(): bool
    {
        return in_array($this, [self::Pending, self::Failed], true);
    }
}
