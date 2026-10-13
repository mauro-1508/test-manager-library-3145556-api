<?php

declare(strict_types=1);

namespace App\Application\Errors;

use App\Domain\DomainError;

final class IsbnTaken extends DomainError
{
    public function __construct()
    {
        parent::__construct('ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.');
    }
}
