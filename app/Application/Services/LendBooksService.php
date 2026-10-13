<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\LoanView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\ConcurrencyConflict;
use App\Application\Ports\Outbound\LoanReader;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Book;
use App\Domain\BookNotAvailable;
use App\Domain\DomainValidationError;
use App\Domain\LendingPolicy;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

final class LendBooksService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
        private readonly LoanReader $loanReader,
        private readonly LoanRepository $loans,
        private readonly LendingPolicy $policy,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param list<string> $bookIds
     */
    public function execute(string $memberId, array $bookIds, string $loanedByUserId): LoanView
    {
        $this->assertValidLineCount($bookIds);
        $this->members->find($memberId) ?? throw NotFound::member();
        $now = $this->clock->now();
        $this->assertMemberMayBorrow($memberId, count($bookIds), $now);

        $booksById = $this->loadBooks($bookIds);
        $categoryNames = $this->categoryNamesById();

        $loan = Loan::create($memberId, $loanedByUserId, $now);
        foreach ($bookIds as $bookId) {
            $book = $booksById[$bookId];
            $loan->addItem($book, $categoryNames[$book->categoryId()] ?? throw NotFound::category());
        }

        $this->saveOrFail($loan, $booksById);

        return LoanView::of($loan, $now);
    }

    /**
     * @param list<string> $bookIds
     */
    private function assertValidLineCount(array $bookIds): void
    {
        if ($bookIds === [] || count($bookIds) > Loan::MAX_ITEMS) {
            throw new DomainValidationError('Un préstamo debe tener entre 1 y ' . Loan::MAX_ITEMS . ' libros.');
        }
    }

    private function assertMemberMayBorrow(string $memberId, int $requested, DateTimeImmutable $now): void
    {
        $unreturned = $this->loanReader->findUnreturnedItemsOfMember($memberId);
        $hasOverdue = array_filter($unreturned, static fn (LoanItem $item): bool => $item->isOverdue($now)) !== [];

        $this->policy->assertCanBorrow(count($unreturned), $hasOverdue, $requested);
    }

    /**
     * @param list<string> $bookIds
     * @return array<string, Book>
     */
    private function loadBooks(array $bookIds): array
    {
        $byId = [];
        foreach ($this->books->findActiveByIds(array_values(array_unique($bookIds))) as $book) {
            $byId[$book->id] = $book;
        }
        foreach ($bookIds as $bookId) {
            $byId[$bookId] ?? throw NotFound::book();
        }

        return $byId;
    }

    /**
     * @return array<string, string>
     */
    private function categoryNamesById(): array
    {
        $names = [];
        foreach ($this->categories->all() as $category) {
            $names[$category->id] = $category->name;
        }

        return $names;
    }

    /**
     * @param array<string, Book> $booksById
     */
    private function saveOrFail(Loan $loan, array $booksById): void
    {
        try {
            $this->loans->save($loan, array_values($booksById));
        } catch (ConcurrencyConflict $conflict) {
            $conflicted = $booksById[$conflict->bookId] ?? null;
            throw new BookNotAvailable($conflicted?->title() ?? '');
        }
    }
}
