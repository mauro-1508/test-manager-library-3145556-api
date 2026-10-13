<?php

declare(strict_types=1);

namespace App\Application\Errors;

use App\Domain\DomainError;

final class UsernameTaken extends DomainError
{
    public function __construct()
    {
        parent::__construct('USERNAME_TAKEN', 'Ya existe un usuario con ese nombre.');
    }
}
