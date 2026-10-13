<?php

declare(strict_types=1);

namespace App\Domain;

final class DuplicateBookInLoan extends DomainError
{
    public function __construct(string $bookTitle)
    {
        parent::__construct('DUPLICATE_BOOK_IN_LOAN', "El libro «{$bookTitle}» está repetido en el préstamo.");
    }
}
