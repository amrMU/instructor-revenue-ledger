<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderTransactionStatus: string
{
    case Initiated = 'initiated';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
