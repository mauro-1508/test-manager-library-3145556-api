<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Loan;
use App\Domain\LoanStatus;
use DateTimeImmutable;

final readonly class LoanView
{
    public function __construct(public Loan $loan, public LoanStatus $status)
    {
    }

    public static function of(Loan $loan, DateTimeImmutable $now): self
    {
        return new self($loan, $loan->status($now));
    }
}
