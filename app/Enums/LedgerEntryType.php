<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerEntryType: string
{
    case Earning = 'earning';
    case RefundAdjustment = 'refund_adjustment';
    case Correction = 'correction';
}
