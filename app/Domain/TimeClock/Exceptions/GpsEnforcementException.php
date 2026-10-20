<?php

namespace App\Domain\TimeClock\Exceptions;

use RuntimeException;

class GpsEnforcementException extends RuntimeException
{
    public function __construct(
        string $message,
        protected string $reasonCode = 'gps_enforcement_failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }
}
