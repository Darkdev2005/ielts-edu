<?php

namespace App\Services\AI;

use RuntimeException;

class GroqException extends RuntimeException
{
    public function __construct(
        string $message,
        public int $statusCode = 500,
        public array $payload = [],
    ) {
        parent::__construct($message, $statusCode);
    }
}
