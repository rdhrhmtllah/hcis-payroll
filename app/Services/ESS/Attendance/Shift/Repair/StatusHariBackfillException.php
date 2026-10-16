<?php

namespace App\Services\ESS\Attendance\Shift\Repair;

use RuntimeException;

class StatusHariBackfillException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
