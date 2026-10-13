<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\OverdueCandidate;
use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\LoanRepository;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

/**
 * Saves a loan and its books as one unit, like the real transaction would.
 */
final class InMemoryLoans implements LoanRepository, LoanReader
{
    private const DAY_FORMAT = 'Y-m-d';

    /** @var array<string, Loan> */
    private array $byId = [];

    /** Test switch: every save reports a concurrent change on this book and stores nothing. */
    public ?string $conflictOnBookId = null;

    public function __construct(private readonly InMemoryBooks $books, private readonly InMemoryMembers $members)
    {
    }

    public function save(Loan $loan, array $books): void
    {
        if ($this->conflictOnBookId !== null) {
            throw new ConcurrencyConflict($this->conflictOnBookId);
        }
        $this->byId[$loan->id] = clone $loan;
        foreach ($books as $book) {
            $this->books->save($book);
        }
    }

    public function find(string $id): ?Loan
    {
        return isset($this->byId[$id]) ? clone $this->byId[$id] : null;
    }

    public function search(LoanFilter $filter, PageRequest $request, DateTimeImmutable $now): Page
    {
        $matches = array_filter($this->byId, fn (Loan $loan): bool => $this->matches($loan, $filter, $now));
        usort($matches, static fn (Loan $a, Loan $b): int => $b->loanedAt <=> $a->loanedAt);

        return Paginator::slice(array_map(static fn (Loan $loan): Loan => clone $loan, $matches), $request);
    }

    public function findUnreturnedItemsOfMember(string $memberId): array
    {
        $items = [];
        foreach ($this->byId as $loan) {
            if ($loan->memberId === $memberId) {
                array_push($items, ...$this->unreturned($loan));
            }
        }

        return $items;
    }

    public function findOverdueCandidates(DateTimeImmutable $now): array
    {
        $candidates = [];
        foreach ($this->byId as $loan) {
            $memberName = $this->members->find($loan->memberId)?->fullName ?? '';
            foreach ($this->unreturned($loan) as $item) {
                if ($item->isOverdue($now)) {
                    $candidates[] = new OverdueCandidate($loan->id, $memberName, $item);
                }
            }
        }

        return $candidates;
    }

    public function hasOpenLoansForBook(string $bookId): bool
    {
        foreach ($this->byId as $loan) {
            foreach ($this->unreturned($loan) as $item) {
                if ($item->bookId === $bookId) {
                    return true;
                }
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->byId);
    }

    /**
     * Test helper and source of the fake report reader: the saved loans, nothing else.
     *
     * @return list<Loan>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }

    /**
     * @return list<LoanItem>
     */
    private function unreturned(Loan $loan): array
    {
        return array_values(array_filter($loan->items(), static fn (LoanItem $item): bool => !$item->isReturned()));
    }

    private function matches(Loan $loan, LoanFilter $filter, DateTimeImmutable $now): bool
    {
        $day = $loan->loanedAt->format(self::DAY_FORMAT);

        return ($filter->from === null || $day >= $filter->from->format(self::DAY_FORMAT))
            && ($filter->to === null || $day <= $filter->to->format(self::DAY_FORMAT))
            && ($filter->status === null || $loan->status($now) === $filter->status)
            && ($filter->memberId === null || $loan->memberId === $filter->memberId);
    }
}
