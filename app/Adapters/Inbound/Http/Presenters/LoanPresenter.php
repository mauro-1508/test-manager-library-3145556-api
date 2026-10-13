<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Presenters;

use App\Application\Dto\LoanView;
use App\Application\Dto\OverdueLine;
use App\Domain\LoanItem;

final class LoanPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(LoanView $view): array
    {
        $loan = $view->loan;

        return [
            'id' => $loan->id,
            'member_id' => $loan->memberId,
            'loaned_at' => Iso8601::utc($loan->loanedAt),
            'status' => $view->status->value,
            'items' => array_map(self::item(...), $loan->items()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(LoanItem $item): array
    {
        return [
            'id' => $item->id,
            'book_id' => $item->bookId,
            'book_title' => $item->bookTitle,
            'isbn' => $item->isbn,
            'category_name' => $item->categoryName,
            'due_at' => Iso8601::utc($item->dueAt),
            'returned_at' => Iso8601::utcOrNull($item->returnedAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function overdueLine(OverdueLine $line): array
    {
        return [
            'loan_id' => $line->loanId,
            'item_id' => $line->itemId,
            'book_title' => $line->bookTitle,
            'member_name' => $line->memberName,
            'due_at' => Iso8601::utc($line->dueAt),
            'days_late' => $line->daysLate,
        ];
    }
}
