<?php

declare(strict_types=1);

namespace App\Domain;

final class InvalidDateRange extends DomainError
{
    public function __construct()
    {
        parent::__construct('INVALID_DATE_RANGE', 'La fecha final no puede ser anterior a la fecha inicial.');
    }
}
