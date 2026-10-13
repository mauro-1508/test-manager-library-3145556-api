<?php

declare(strict_types=1);

namespace App\Domain;

final class MemberHasOverdue extends DomainError
{
    public function __construct()
    {
        parent::__construct('MEMBER_HAS_OVERDUE', 'El socio tiene libros vencidos y no puede pedir más.');
    }
}
