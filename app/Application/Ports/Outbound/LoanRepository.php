<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Book;
use App\Domain\Loan;

interface LoanRepository
{
    /**
     * Persists the loan and the given books as ONE unit (one transaction).
     *
     * @param list<Book> $books
     * @throws ConcurrencyConflict when any of the books changed since it was read; nothing is saved
     */
    public function save(Loan $loan, array $books): void;

    public function find(string $id): ?Loan;
}
