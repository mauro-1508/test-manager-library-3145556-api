<?php

declare(strict_types=1);

namespace App\Application\Errors;

use App\Domain\DomainError;

final class NotFound extends DomainError
{
    private function __construct(string $userMessage)
    {
        parent::__construct('NOT_FOUND', $userMessage);
    }

    public static function book(): self
    {
        return new self('El libro solicitado no existe.');
    }

    public static function member(): self
    {
        return new self('El socio solicitado no existe.');
    }

    public static function loan(): self
    {
        return new self('El préstamo solicitado no existe.');
    }

    public static function loanItem(): self
    {
        return new self('La línea del préstamo solicitada no existe.');
    }

    public static function category(): self
    {
        return new self('La categoría solicitada no existe.');
    }
}
