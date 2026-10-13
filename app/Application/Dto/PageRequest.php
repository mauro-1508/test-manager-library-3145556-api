<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\DomainValidationError;

final readonly class PageRequest
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_SIZE = 20;
    public const MAX_SIZE = 50;

    public function __construct(public int $page = self::DEFAULT_PAGE, public int $size = self::DEFAULT_SIZE)
    {
        if ($page < 1) {
            throw new DomainValidationError('La página debe ser mayor o igual a 1.');
        }
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw new DomainValidationError('El tamaño de página debe estar entre 1 y ' . self::MAX_SIZE . '.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->size;
    }
}
