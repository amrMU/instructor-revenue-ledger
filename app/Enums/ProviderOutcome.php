<?php

declare(strict_types=1);

namespace App\Enums;

enum ProviderOutcome: string
{
    case Success = 'success';
    case PermanentFailure = 'permanent_failure';
    case TimeoutAfterSuccess = 'timeout_after_success';
}
