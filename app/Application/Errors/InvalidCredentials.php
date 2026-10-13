<?php

declare(strict_types=1);

namespace App\Application\Errors;

use App\Domain\DomainError;

final class InvalidCredentials extends DomainError
{
    public function __construct()
    {
        parent::__construct('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
    }
}
