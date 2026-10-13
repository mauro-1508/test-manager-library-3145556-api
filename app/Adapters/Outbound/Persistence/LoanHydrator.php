<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Domain\Loan;
use App\Domain\LoanItem;
use Illuminate\Support\Facades\DB;

/**
 * Maps loan and loan_item rows to domain objects (shared by the loan repository and the loan reader).
 */
final class LoanHydrator
{
    /**
     * @param list<object> $loanRows rows of library.loan
     * @return list<Loan> same order as the rows
     */
    public static function loans(array $loanRows): array
    {
        if ($loanRows === []) {
            return [];
        }
        $itemsByLoan = [];
        $rows = DB::table('library.loan_item')
            ->whereIn('loan_id', array_map(static fn (object $row): string => $row->id, $loanRows))
            ->orderBy('book_title')->orderBy('id')
            ->get();
        foreach ($rows as $row) {
            $itemsByLoan[$row->loan_id][] = self::item($row);
        }

        return array_map(
            static fn (object $row): Loan => new Loan(
                $row->id,
                $row->member_id,
                $row->loaned_by_user_id,
                PgTime::fromDb($row->loaned_at),
                $itemsByLoan[$row->id] ?? [],
            ),
            $loanRows,
        );
    }

    public static function item(object $row): LoanItem
    {
        return new LoanItem(
            $row->id,
            $row->book_id,
            $row->book_title,
            $row->isbn,
            $row->category_name,
            PgTime::fromDb($row->due_at),
            PgTime::fromDbNullable($row->returned_at),
        );
    }
}
