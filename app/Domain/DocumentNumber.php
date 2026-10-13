<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class DocumentNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new DomainValidationError('El documento es obligatorio.');
        }
        $this->value = $trimmed;
    }
}
