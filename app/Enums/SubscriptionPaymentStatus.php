<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionPaymentStatus: string
{
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
