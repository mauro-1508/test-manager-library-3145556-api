<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\BookView;
use App\Application\Errors\IsbnTaken;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Book;
use App\Domain\DomainValidationError;
use App\Domain\Isbn;
use App\Domain\Quantity;

final class CreateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function execute(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): BookView
    {
        $category = $this->categories->find($categoryId)
            ?? throw new DomainValidationError('La categoría indicada no existe.');
        $isbnValue = new Isbn($isbn);
        if ($this->books->isIsbnTakenByActiveBook($isbnValue)) {
            throw new IsbnTaken();
        }

        $book = Book::create($title, $author, $isbnValue, $category->id, new Quantity($totalCopies));
        $this->books->save($book);

        return new BookView($book, $category);
    }
}
