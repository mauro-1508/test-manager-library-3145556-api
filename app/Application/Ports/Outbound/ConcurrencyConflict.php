<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use RuntimeException;

/**
 * Raised by LoanRepository::save when a book was changed by someone else since it was read (ADR-002).
 */
final class ConcurrencyConflict extends RuntimeException
{
    public function __construct(public readonly string $bookId)
    {
        parent::__construct("Concurrent change detected on book {$bookId}.");
    }
}
