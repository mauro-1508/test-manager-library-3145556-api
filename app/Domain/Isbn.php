<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Isbn
{
    private const LENGTH = 13;

    public string $value;

    public function __construct(string $value)
    {
        if (preg_match('/^\d{' . self::LENGTH . '}$/', $value) !== 1) {
            throw new DomainValidationError('El ISBN debe tener exactamente 13 dígitos.');
        }
        $this->value = $value;
    }
}
