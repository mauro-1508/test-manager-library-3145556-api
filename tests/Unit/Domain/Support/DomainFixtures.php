<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Support;

use App\Domain\Book;
use App\Domain\Isbn;
use App\Domain\Quantity;
use DateTimeImmutable;
use DateTimeZone;

final class DomainFixtures
{
    private const BASE_ISBN = 9780307474728;

    private static int $isbnOffset = 0;

    public static function book(int $total = 2, string $title = 'Cien años de soledad'): Book
    {
        $isbn = new Isbn((string) (self::BASE_ISBN + self::$isbnOffset++));

        return Book::create($title, 'Gabriel García Márquez', $isbn, 'category-1', new Quantity($total));
    }

    public static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }
}
