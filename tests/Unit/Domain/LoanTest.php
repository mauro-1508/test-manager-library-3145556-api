<?php

declare(strict_types=1);

use App\Domain\BookNotAvailable;
use App\Domain\DomainValidationError;
use App\Domain\DuplicateBookInLoan;
use App\Domain\ItemAlreadyReturned;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use Tests\Unit\Domain\Support\DomainFixtures;

function newLoan(): Loan
{
    return Loan::create('member-1', 'user-1', DomainFixtures::at('2026-10-01 12:00'));
}

it('adds an item taking one copy and freezing title, isbn and category', function () {
    $book = DomainFixtures::book(2, 'Rayuela');
    $loan = newLoan();

    $item = $loan->addItem($book, 'Novela');

    expect($book->availableCopies())->toBe(1)
        ->and($item->bookTitle)->toBe('Rayuela')
        ->and($item->isbn)->toBe($book->isbn->value)
        ->and($item->categoryName)->toBe('Novela')
        ->and($item->dueAt)->toEqual(DomainFixtures::at('2026-10-15 12:00'))
        ->and($loan->dueAt())->toEqual(DomainFixtures::at('2026-10-15 12:00'));
});

it('keeps the frozen title when the catalogue changes later', function () {
    $book = DomainFixtures::book(2, 'Rayuela');
    $loan = newLoan();
    $loan->addItem($book, 'Novela');

    expect($loan->items()[0]->bookTitle)->toBe('Rayuela');
});

it('rejects a repeated book without taking a second copy', function () {
    $book = DomainFixtures::book(2);
    $loan = newLoan();
    $loan->addItem($book, 'Novela');

    expect(fn () => $loan->addItem($book, 'Novela'))->toThrow(DuplicateBookInLoan::class)
        ->and($book->availableCopies())->toBe(1);
});

it('rejects a book without available copies and adds no item', function () {
    $book = DomainFixtures::book(1);
    $book->withdraw();
    $loan = newLoan();

    expect(fn () => $loan->addItem($book, 'Novela'))->toThrow(BookNotAvailable::class)
        ->and($loan->items())->toBeEmpty();
});

it('accepts at most three items', function () {
    $loan = newLoan();
    foreach (range(1, 3) as $ignored) {
        $loan->addItem(DomainFixtures::book(), 'Novela');
    }
    $fourth = DomainFixtures::book();

    expect(fn () => $loan->addItem($fourth, 'Novela'))->toThrow(DomainValidationError::class)
        ->and($fourth->availableCopies())->toBe(2);
});

it('is open before the due date, overdue after it and closed when all are returned', function () {
    $loan = newLoan();
    $book = DomainFixtures::book();
    $item = $loan->addItem($book, 'Novela');

    expect($loan->status(DomainFixtures::at('2026-10-15 12:00')))->toBe(LoanStatus::Open)
        ->and($loan->status(DomainFixtures::at('2026-10-15 12:01')))->toBe(LoanStatus::Overdue)
        ->and($loan->items()[0]->daysLate(DomainFixtures::at('2026-10-18 13:00')))->toBe(3);

    $loan->returnItem($item->id, $book, DomainFixtures::at('2026-10-20 09:00'));

    expect($loan->status(DomainFixtures::at('2026-10-21 09:00')))->toBe(LoanStatus::Closed)
        ->and($loan->items()[0]->daysLate(DomainFixtures::at('2026-10-21 09:00')))->toBe(0);
});

it('stays open or overdue while some item is unreturned', function () {
    $loan = newLoan();
    $first = DomainFixtures::book();
    $second = DomainFixtures::book();
    $firstItem = $loan->addItem($first, 'Novela');
    $loan->addItem($second, 'Novela');

    $loan->returnItem($firstItem->id, $first, DomainFixtures::at('2026-10-02 09:00'));

    expect($loan->status(DomainFixtures::at('2026-10-05 09:00')))->toBe(LoanStatus::Open)
        ->and($loan->status(DomainFixtures::at('2026-11-05 09:00')))->toBe(LoanStatus::Overdue);
});

it('returns an item restocking the book once', function () {
    $loan = newLoan();
    $book = DomainFixtures::book(2);
    $item = $loan->addItem($book, 'Novela');
    $now = DomainFixtures::at('2026-10-03 10:00');

    $returned = $loan->returnItem($item->id, $book, $now);

    expect($returned->returnedAt)->toEqual($now)->and($book->availableCopies())->toBe(2);
});

it('rejects returning the same item twice without restocking again', function () {
    $loan = newLoan();
    $book = DomainFixtures::book(2);
    $item = $loan->addItem($book, 'Novela');
    $loan->returnItem($item->id, $book, DomainFixtures::at('2026-10-03 10:00'));

    expect(fn () => $loan->returnItem($item->id, $book, DomainFixtures::at('2026-10-04 10:00')))
        ->toThrow(ItemAlreadyReturned::class)
        ->and($book->availableCopies())->toBe(2);
});

it('rejects returning an item that is not in the loan', function () {
    newLoan()->returnItem('unknown', DomainFixtures::book(), DomainFixtures::at('2026-10-03 10:00'));
})->throws(DomainValidationError::class);
