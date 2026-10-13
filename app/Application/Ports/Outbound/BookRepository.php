<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Dto\Page;
use App\Application\Dto\PageRequest;
use App\Domain\Book;
use App\Domain\Isbn;

interface BookRepository
{
    /**
     * Inserts or updates the book (soft-deleted state and cover included).
     */
    public function save(Book $book): void;

    /**
     * Only books that are not soft-deleted.
     */
    public function findActive(string $id): ?Book;

    /**
     * Single batch read (Q3). Unknown or deleted ids are simply absent from the result.
     *
     * @param list<string> $ids
     * @return list<Book>
     */
    public function findActiveByIds(array $ids): array;

    /**
     * Active books whose title or author contains $text, optionally of one category, ordered by title.
     *
     * @return Page of Book
     */
    public function search(?string $text, ?string $categoryId, PageRequest $request): Page;

    public function isIsbnTakenByActiveBook(Isbn $isbn): bool;
}
