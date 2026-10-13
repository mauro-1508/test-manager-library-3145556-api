<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Dto\MostBorrowedRow;
use App\Application\Ports\Outbound\ReportReader;
use App\Domain\DateRange;

/**
 * Aggregates the values frozen in the saved loan lines; it never looks at the catalogue (ADR-004).
 */
final class InMemoryReportReader implements ReportReader
{
    private const DAY_FORMAT = 'Y-m-d';

    public function __construct(private readonly InMemoryLoans $loans)
    {
    }

    public function mostBorrowed(DateRange $range): array
    {
        $groups = [];
        foreach ($this->loans->all() as $loan) {
            $day = $loan->loanedAt->format(self::DAY_FORMAT);
            if ($day < $range->start->format(self::DAY_FORMAT) || $day > $range->end->format(self::DAY_FORMAT)) {
                continue;
            }
            foreach ($loan->items() as $item) {
                $key = $item->bookId . '|' . $item->bookTitle . '|' . $item->categoryName;
                $previous = isset($groups[$key]) ? $groups[$key]->timesBorrowed : 0;
                $groups[$key] = new MostBorrowedRow($item->bookId, $item->bookTitle, $item->categoryName, $previous + 1);
            }
        }

        $rows = array_values($groups);
        usort(
            $rows,
            static fn (MostBorrowedRow $a, MostBorrowedRow $b): int => [$b->timesBorrowed, $a->bookTitle]
                <=> [$a->timesBorrowed, $b->bookTitle],
        );

        return $rows;
    }
}
