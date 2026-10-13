<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Book;

final readonly class BookView
{
    public function __construct(public Book $book, public Category $category)
    {
    }
}
