<?php

namespace App\Services\AI;

use RuntimeException;

class GeminiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $response = [],
    ) {
        parent::__construct($message, $statusCode);
    }
}
