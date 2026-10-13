<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Errors;

use App\Domain\DomainError;

final class ForbiddenRole extends DomainError
{
    public function __construct()
    {
        parent::__construct(ErrorCode::FORBIDDEN_ROLE, 'Tu rol no tiene permiso para realizar esta acción.');
    }
}
