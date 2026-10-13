<?php

declare(strict_types=1);

namespace App\Application\Errors;

use App\Domain\DomainError;

final class DocumentTaken extends DomainError
{
    public function __construct()
    {
        parent::__construct('DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.');
    }
}
