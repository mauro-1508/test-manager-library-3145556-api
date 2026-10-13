<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

use App\Domain\DomainError;

final class Unauthenticated extends DomainError
{
    public function __construct()
    {
        parent::__construct(ErrorCode::INVALID_CREDENTIALS, 'Falta el token de acceso o es inválido o está vencido.');
    }
}
