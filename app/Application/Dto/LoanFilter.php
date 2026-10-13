<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\InvalidDateRange;
use App\Domain\LoanStatus;
use DateTimeImmutable;

/**
 * Filters of the loan listing (HU-08). `from` and `to` are calendar days (UTC), both inclusive.
 */
final readonly class LoanFilter
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?LoanStatus $status = null,
        public ?string $memberId = null,
    ) {
        if ($from !== null && $to !== null && $to < $from) {
            throw new InvalidDateRange();
        }
    }
}
