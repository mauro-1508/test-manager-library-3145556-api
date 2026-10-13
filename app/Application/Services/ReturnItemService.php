<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Book;
use App\Domain\ItemAlreadyReturned;
use App\Domain\Loan;
use App\Domain\LoanItem;

final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly BookRepository $books,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw NotFound::loan();
        $bookId = $this->bookIdOfItem($loan->items(), $itemId);
        $book = $this->books->findActive($bookId) ?? throw NotFound::book();

        $returned = $loan->returnItem($itemId, $book, $this->clock->now());
        $this->saveOrFail($loan, $book);

        return $returned;
    }

    /**
     * A conflict on a return means someone else returned the same line first (CA-07.2).
     */
    private function saveOrFail(Loan $loan, Book $book): void
    {
        try {
            $this->loans->save($loan, [$book]);
        } catch (ConcurrencyConflict) {
            throw new ItemAlreadyReturned();
        }
    }

    /**
     * @param list<LoanItem> $items
     */
    private function bookIdOfItem(array $items, string $itemId): string
    {
        foreach ($items as $item) {
            if ($item->id === $itemId) {
                return $item->bookId;
            }
        }

        throw NotFound::loanItem();
    }
}
