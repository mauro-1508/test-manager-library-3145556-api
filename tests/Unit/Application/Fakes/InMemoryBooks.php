<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fakes;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;
use App\Domain\Isbn;

/**
 * Stores and returns clones so that unsaved changes never leak into the "database".
 */
final class InMemoryBooks implements BookRepository
{
    /** @var array<string, Book> */
    private array $byId = [];

    public function save(Book $book): void
    {
        $this->byId[$book->id] = clone $book;
    }

    public function findActive(string $id): ?Book
    {
        $book = $this->byId[$id] ?? null;

        return $book === null || $book->isDeleted() ? null : clone $book;
    }

    public function findActiveByIds(array $ids): array
    {
        return array_values(array_filter(array_map(fn (string $id): ?Book => $this->findActive($id), $ids)));
    }

    public function search(?string $text, ?string $categoryId, PageRequest $request): Page
    {
        $matches = array_filter(
            $this->byId,
            static fn (Book $book): bool => !$book->isDeleted()
                && ($categoryId === null || $book->categoryId() === $categoryId)
                && ($text === null || stripos($book->title(), $text) !== false || stripos($book->author(), $text) !== false),
        );
        usort($matches, static fn (Book $a, Book $b): int => strcasecmp($a->title(), $b->title()));

        return Paginator::slice(array_map(static fn (Book $book): Book => clone $book, $matches), $request);
    }

    public function isIsbnTakenByActiveBook(Isbn $isbn): bool
    {
        foreach ($this->byId as $book) {
            if (!$book->isDeleted() && $book->isbn->value === $isbn->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test helper: reads the stored state, deleted books included.
     */
    public function stored(string $id): Book
    {
        return clone $this->byId[$id];
    }
}
