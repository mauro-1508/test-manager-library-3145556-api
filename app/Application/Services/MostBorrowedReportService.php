<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\MostBorrowedRow;
use App\Application\Ports\Outbound\ReportReader;
use App\Domain\DateRange;
use DateTimeImmutable;

final class MostBorrowedReportService
{
    public function __construct(private readonly ReportReader $reports)
    {
    }

    /**
     * @param DateTimeImmutable $from first day of the range (inclusive)
     * @param DateTimeImmutable $to last day of the range (inclusive)
     * @return list<MostBorrowedRow>
     */
    public function execute(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->reports->mostBorrowed(new DateRange($from, $to));
    }
}
