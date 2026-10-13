<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Errors\BookHasOpenLoans;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanReader;

final class DeleteBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly LoanReader $loans,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $bookId): void
    {
        $book = $this->books->findActive($bookId) ?? throw NotFound::book();
        if ($this->loans->hasOpenLoansForBook($bookId)) {
            throw new BookHasOpenLoans();
        }

        $book->softDelete($this->clock->now());
        $this->books->save($book);
    }
}
