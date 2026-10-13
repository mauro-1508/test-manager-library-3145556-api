<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\BookView;
use App\Application\Errors\NotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\DomainValidationError;
use App\Domain\Quantity;

final class UpdateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function execute(string $bookId, string $title, string $author, string $categoryId, int $totalCopies): BookView
    {
        $book = $this->books->findActive($bookId) ?? throw NotFound::book();
        $category = $this->categories->find($categoryId)
            ?? throw new DomainValidationError('La categoría indicada no existe.');

        $book->replaceDetails($title, $author, $category->id, new Quantity($totalCopies));
        $this->books->save($book);

        return new BookView($book, $category);
    }
}
