<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\OverdueCandidate;
use App\Application\Dto\OverdueLine;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanReader;

final class OverdueService
{
    public function __construct(
        private readonly LoanReader $loans,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<OverdueLine> most days late first
     */
    public function execute(): array
    {
        $now = $this->clock->now();
        $lines = array_map(
            static fn (OverdueCandidate $candidate): OverdueLine => new OverdueLine(
                $candidate->loanId,
                $candidate->item->id,
                $candidate->item->bookTitle,
                $candidate->memberName,
                $candidate->item->dueAt,
                $candidate->item->daysLate($now),
            ),
            $this->loans->findOverdueCandidates($now),
        );
        usort($lines, static fn (OverdueLine $a, OverdueLine $b): int => $b->daysLate <=> $a->daysLate);

        return $lines;
    }
}
