<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Quantity
{
    public function __construct(public int $value)
    {
        if ($value <= 0) {
            throw new DomainValidationError('La cantidad debe ser mayor que cero.');
        }
    }
}
