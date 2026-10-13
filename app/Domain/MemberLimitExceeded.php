<?php

declare(strict_types=1);

namespace App\Domain;

final class MemberLimitExceeded extends DomainError
{
    public function __construct(public readonly int $currentCount, int $limit)
    {
        parent::__construct(
            'MEMBER_LIMIT_EXCEEDED',
            "El socio ya tiene {$currentCount} libros sin devolver; el máximo es {$limit}.",
        );
    }
}
