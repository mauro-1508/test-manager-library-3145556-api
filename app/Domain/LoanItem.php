<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class LoanItem
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        public string $id,
        public string $bookId,
        public string $bookTitle,
        public string $isbn,
        public string $categoryName,
        public DateTimeImmutable $dueAt,
        public ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $this->dueAt < $now;
    }

    public function daysLate(DateTimeImmutable $now): int
    {
        if (!$this->isOverdue($now)) {
            return 0;
        }

        return intdiv($now->getTimestamp() - $this->dueAt->getTimestamp(), self::SECONDS_PER_DAY);
    }

    public function markReturned(DateTimeImmutable $returnedAt): self
    {
        return new self(
            $this->id,
            $this->bookId,
            $this->bookTitle,
            $this->isbn,
            $this->categoryName,
            $this->dueAt,
            $returnedAt,
        );
    }
}
