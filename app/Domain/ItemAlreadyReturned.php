<?php

declare(strict_types=1);

namespace App\Domain;

final class ItemAlreadyReturned extends DomainError
{
    public function __construct()
    {
        parent::__construct('ITEM_ALREADY_RETURNED', 'Esta línea del préstamo ya fue devuelta.');
    }
}
