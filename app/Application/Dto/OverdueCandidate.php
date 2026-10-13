<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\LoanItem;

/**
 * Raw row read from persistence: an unreturned line plus the data of its loan that the overdue list needs.
 */
final readonly class OverdueCandidate
{
    public function __construct(public string $loanId, public string $memberName, public LoanItem $item)
    {
    }
}
