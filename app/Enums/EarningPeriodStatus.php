<?php

declare(strict_types=1);

namespace App\Enums;

enum EarningPeriodStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
