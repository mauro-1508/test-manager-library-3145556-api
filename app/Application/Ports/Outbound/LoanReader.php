<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\OverdueCandidate;
use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Domain\LoanItem;
use DateTimeImmutable;

interface LoanReader
{
    /**
     * Loans matching the filter (status computed as of $now), newest loaned_at first.
     *
     * @return Page of Loan
     */
    public function search(LoanFilter $filter, PageRequest $request, DateTimeImmutable $now): Page;

    /**
     * @return list<LoanItem> lines of the member that are not returned yet (Q7)
     */
    public function findUnreturnedItemsOfMember(string $memberId): array;

    /**
     * @return list<OverdueCandidate> unreturned lines with due_at before $now (Q8), any order
     */
    public function findOverdueCandidates(DateTimeImmutable $now): array;

    public function hasOpenLoansForBook(string $bookId): bool;
}
