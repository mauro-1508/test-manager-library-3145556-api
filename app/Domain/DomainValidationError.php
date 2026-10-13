<?php

declare(strict_types=1);

namespace App\Domain;

final class DomainValidationError extends DomainError
{
    public function __construct(string $userMessage)
    {
        parent::__construct('VALIDATION_ERROR', $userMessage);
    }
}
