<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class DateRange
{
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end)
    {
        if ($end < $start) {
            throw new InvalidDateRange();
        }
    }
}
