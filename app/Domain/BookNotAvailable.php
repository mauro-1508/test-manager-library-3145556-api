<?php

declare(strict_types=1);

namespace App\Domain;

final class BookNotAvailable extends DomainError
{
    public function __construct(string $bookTitle)
    {
        parent::__construct('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$bookTitle}».");
    }
}
