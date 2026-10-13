<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\MostBorrowedRow;
use App\Domain\DateRange;

interface ReportReader
{
    /**
     * Loans whose loaned_at day (UTC) falls in the range, both ends inclusive, grouped by the values frozen in the line
     * (book_id, book_title, category_name). Ordered by times borrowed descending, then title.
     *
     * @return list<MostBorrowedRow>
     */
    public function mostBorrowed(DateRange $range): array;
}
