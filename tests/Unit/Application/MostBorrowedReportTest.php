<?php

declare(strict_types=1);

use App\Application\Services\MostBorrowedReportService;
use App\Application\Services\UpdateBookService;
use App\Domain\InvalidDateRange;
use Tests\Unit\Application\Fakes\InMemoryCategories;
use Tests\Unit\Application\Fakes\Library;

function reportService(Library $lib): MostBorrowedReportService
{
    return new MostBorrowedReportService($lib->reports);
}

function day(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date, new DateTimeZone('UTC'));
}

it('counts one row per book and frozen category, ordered by times borrowed then title (CA-10.1)', function () {
    $lib = new Library('2026-10-05T10:00:00Z');
    $popular = $lib->addBook('Zeta popular', 5);
    $quiet = $lib->addBook('Beta tranquilo', 5);
    $tie = $lib->addBook('Alfa empate', 5);
    foreach ([1, 2, 3] as $n) {
        $lib->lendService()->execute($lib->addMember("Socio {$n}")->id, [$popular->id], 'user-1');
    }
    $lib->lendService()->execute($lib->addMember('Socio 4')->id, [$quiet->id, $tie->id], 'user-1');

    $rows = reportService($lib)->execute(day('2026-10-01'), day('2026-10-31'));

    expect(array_map(fn ($r) => [$r->bookTitle, $r->timesBorrowed], $rows))
        ->toBe([['Zeta popular', 3], ['Alfa empate', 1], ['Beta tranquilo', 1]])
        ->and($rows[0]->categoryName)->toBe('Novela')
        ->and($rows[0]->bookId)->toBe($popular->id);
});

it('includes both end days and excludes loans outside the range', function () {
    $lib = new Library('2026-09-30T23:00:00Z');
    $book = $lib->addBook('Fronteras', 9);
    $lend = fn (string $moment) => $lib->clock->set($moment) ?? $lib->lendService()->execute($lib->addMember()->id, [$book->id], 'user-1');
    $lend('2026-09-30T23:00:00Z');
    $lend('2026-10-01T00:00:00Z');
    $lend('2026-10-31T23:59:59Z');
    $lend('2026-11-01T00:00:00Z');

    $rows = reportService($lib)->execute(day('2026-10-01'), day('2026-10-31'));

    expect($rows[0]->timesBorrowed)->toBe(2);
});

it('rejects an end date before the start with INVALID_DATE_RANGE (CA-10.2)', function () {
    try {
        reportService(new Library())->execute(day('2026-10-31'), day('2026-10-01'));
        $this->fail('Expected InvalidDateRange.');
    } catch (InvalidDateRange $error) {
        expect($error->errorCode())->toBe('INVALID_DATE_RANGE');
    }
});

it('returns an empty list, not an error, for a range without loans (CA-10.2)', function () {
    expect(reportService(new Library())->execute(day('2026-10-01'), day('2026-10-31')))->toBe([]);
});

it('exposes no member or librarian data (CA-10.3)', function () {
    $properties = array_map(
        fn (ReflectionProperty $property) => $property->getName(),
        (new ReflectionClass(App\Application\Dto\MostBorrowedRow::class))->getProperties(),
    );

    expect($properties)->toBe(['bookId', 'bookTitle', 'categoryName', 'timesBorrowed']);
});

it('keeps the frozen title and category after the book is renamed and recategorised (CA-10.4, ADR-004)', function () {
    $lib = new Library('2026-10-05T10:00:00Z');
    $book = $lib->addBook('Título original', 3, InMemoryCategories::NOVEL_ID);
    $lib->lendService()->execute($lib->addMember()->id, [$book->id], 'user-1');
    $before = reportService($lib)->execute(day('2026-10-01'), day('2026-10-31'));

    (new UpdateBookService($lib->books, $lib->categories))
        ->execute($book->id, 'Título nuevo', 'Autor', InMemoryCategories::SCIENCE_ID, 3);
    $lib->categories->rename(InMemoryCategories::NOVEL_ID, 'Ficción');
    $after = reportService($lib)->execute(day('2026-10-01'), day('2026-10-31'));

    expect($after)->toEqual($before)
        ->and($after[0]->bookTitle)->toBe('Título original')
        ->and($after[0]->categoryName)->toBe('Novela')
        ->and($lib->books->stored($book->id)->title())->toBe('Título nuevo');
});

it('shows two rows when a book changed category within the range (ADR-004)', function () {
    $lib = new Library('2026-10-05T10:00:00Z');
    $book = $lib->addBook('Mudable', 3, InMemoryCategories::NOVEL_ID);
    $lib->lendService()->execute($lib->addMember()->id, [$book->id], 'user-1');
    (new UpdateBookService($lib->books, $lib->categories))
        ->execute($book->id, 'Mudable', 'Autor', InMemoryCategories::HISTORY_ID, 3);
    $lib->lendService()->execute($lib->addMember()->id, [$book->id], 'user-1');

    $rows = reportService($lib)->execute(day('2026-10-01'), day('2026-10-31'));

    expect(array_map(fn ($r) => $r->categoryName, $rows))->toEqualCanonicalizing(['Novela', 'Historia']);
});
