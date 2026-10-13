<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/**
 * Base of every business error raised by the domain.
 * The message is the Spanish text shown to the user; the code is the stable business code.
 */
abstract class DomainError extends RuntimeException
{
    public function __construct(private readonly string $errorCode, string $userMessage)
    {
        parent::__construct($userMessage);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
