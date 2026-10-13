<?php

declare(strict_types=1);

namespace App\Domain;

enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';
}
