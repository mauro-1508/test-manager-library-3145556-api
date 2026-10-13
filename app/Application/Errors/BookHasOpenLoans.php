<?php

declare(strict_types=1);

namespace App\Application\Errors;

use App\Domain\DomainError;

final class BookHasOpenLoans extends DomainError
{
    public function __construct()
    {
        parent::__construct('BOOK_HAS_OPEN_LOANS', 'El libro tiene préstamos abiertos y no se puede dar de baja.');
    }
}
