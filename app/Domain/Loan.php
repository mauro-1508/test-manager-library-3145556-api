<?php

declare(strict_types=1);

namespace App\Domain;

use DateInterval;
use DateTimeImmutable;

final class Loan
{
    public const MAX_ITEMS = 3;
    private const TERM_DAYS = 14;

    /**
     * @param list<LoanItem> $items
     */
    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        private array $items = [],
    ) {
    }

    public static function create(string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt): self
    {
        return new self(Uuid::v4(), $memberId, $loanedByUserId, $loanedAt);
    }

    /**
     * @return list<LoanItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function dueAt(): DateTimeImmutable
    {
        return $this->loanedAt->add(new DateInterval('P' . self::TERM_DAYS . 'D'));
    }

    public function addItem(Book $book, string $categoryName): LoanItem
    {
        $this->assertCanAdd($book);
        $book->withdraw();

        $item = new LoanItem(
            Uuid::v4(),
            $book->id,
            $book->title(),
            $book->isbn->value,
            $categoryName,
            $this->dueAt(),
        );
        $this->items[] = $item;

        return $item;
    }

    public function returnItem(string $itemId, Book $book, DateTimeImmutable $now): LoanItem
    {
        foreach ($this->items as $index => $item) {
            if ($item->id !== $itemId) {
                continue;
            }
            if ($item->isReturned()) {
                throw new ItemAlreadyReturned();
            }
            $book->restock();
            $this->items[$index] = $item->markReturned($now);

            return $this->items[$index];
        }

        throw new DomainValidationError('La línea indicada no pertenece al préstamo.');
    }

    public function status(DateTimeImmutable $now): LoanStatus
    {
        $unreturned = array_filter($this->items, static fn (LoanItem $item): bool => !$item->isReturned());
        if ($unreturned === []) {
            return LoanStatus::Closed;
        }
        foreach ($unreturned as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }

    private function assertCanAdd(Book $book): void
    {
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DuplicateBookInLoan($book->title());
            }
        }
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new DomainValidationError('Un préstamo admite como máximo ' . self::MAX_ITEMS . ' libros.');
        }
    }
}
