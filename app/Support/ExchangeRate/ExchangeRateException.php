<?php

namespace App\Support\ExchangeRate;

class ExchangeRateException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $errorKey = 'exchange_rate_error')
    {
        parent::__construct($message);
    }
}
