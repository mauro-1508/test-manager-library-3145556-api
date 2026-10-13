<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\MostBorrowedRow;
use App\Application\Ports\Outbound\ReportReader;
use App\Domain\DateRange;
use Illuminate\Support\Facades\DB;

/**
 * One aggregate query over the frozen values of the lines; never joins the live catalogue (ADR-004).
 */
final class PgReportReader implements ReportReader
{
    public function mostBorrowed(DateRange $range): array
    {
        $rows = DB::select(
            'SELECT li.book_id, li.book_title, li.category_name, COUNT(*) AS times_borrowed
             FROM library.loan_item li
             JOIN library.loan l ON l.id = li.loan_id
             WHERE l.loaned_at >= ? AND l.loaned_at < ?
             GROUP BY li.book_id, li.book_title, li.category_name
             ORDER BY times_borrowed DESC, li.book_title',
            [PgTime::toDb(PgTime::startOfDay($range->start)), PgTime::toDb(PgTime::startOfNextDay($range->end))],
        );

        return array_map(
            static fn (object $row): MostBorrowedRow => new MostBorrowedRow(
                $row->book_id,
                $row->book_title,
                $row->category_name,
                (int) $row->times_borrowed,
            ),
            $rows,
        );
    }
}
