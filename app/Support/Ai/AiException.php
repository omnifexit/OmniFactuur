<?php

namespace App\Support\Ai;

use RuntimeException;
use Throwable;

/**
 * Domain exception for AI driver failures.
 *
 * Carries a short `errorKey` alongside the human-readable message so the
 * frontend can look up a localized error string. Matches the shape of
 * ExchangeRateException for consistency with the existing driver pattern.
 */
class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorKey = 'server_error',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
