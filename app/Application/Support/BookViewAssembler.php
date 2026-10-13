<?php

declare(strict_types=1);

namespace App\Application\Support;

use App\Application\Dto\BookView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Book;

/**
 * Pairs books with their category so every book use case answers with the same shape.
 */
final class BookViewAssembler
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function assemble(Book $book): BookView
    {
        $category = $this->categories->find($book->categoryId()) ?? throw NotFound::category();

        return new BookView($book, $category);
    }

    /**
     * @param list<Book> $books
     * @return list<BookView>
     */
    public function assembleMany(array $books): array
    {
        $byId = [];
        foreach ($this->categories->all() as $category) {
            $byId[$category->id] = $category;
        }

        return array_map(
            static fn (Book $book): BookView => new BookView(
                $book,
                $byId[$book->categoryId()] ?? throw NotFound::category(),
            ),
            $books,
        );
    }
}
