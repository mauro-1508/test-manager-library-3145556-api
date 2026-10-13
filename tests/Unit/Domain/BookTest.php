<?php

declare(strict_types=1);

use App\Domain\Book;
use App\Domain\BookNotAvailable;
use App\Domain\DomainValidationError;
use App\Domain\Isbn;
use App\Domain\Quantity;
use Tests\Unit\Domain\Support\DomainFixtures;

it('starts with available copies equal to total and trims the title', function () {
    $book = Book::create('  Rayuela ', 'Julio Cortázar', new Isbn('9780307474728'), 'c1', new Quantity(4));

    expect($book->title())->toBe('Rayuela')
        ->and($book->availableCopies())->toBe(4)
        ->and($book->coverKey())->toBeNull();
});

it('rejects an empty title', function () {
    Book::create('   ', 'Autor', new Isbn('9780307474728'), 'c1', new Quantity(1));
})->throws(DomainValidationError::class);

it('withdraw never leaves available below zero', function () {
    $book = DomainFixtures::book(1);
    $book->withdraw();

    expect($book->availableCopies())->toBe(0);
    $book->withdraw();
})->throws(BookNotAvailable::class);

it('restock never leaves available above total', function () {
    $book = DomainFixtures::book(1);
    $book->withdraw();
    $book->restock();

    expect($book->availableCopies())->toBe(1);
    $book->restock();
})->throws(DomainValidationError::class);

it('changing the total adjusts available by the same difference', function () {
    $book = DomainFixtures::book(3);
    $book->withdraw();

    $book->changeTotal(new Quantity(5));
    expect($book->totalCopies())->toBe(5)->and($book->availableCopies())->toBe(4);

    $book->changeTotal(new Quantity(2));
    expect($book->totalCopies())->toBe(2)->and($book->availableCopies())->toBe(1);
});

it('changing the total never leaves available below zero', function () {
    $book = DomainFixtures::book(3);
    $book->withdraw();
    $book->withdraw();

    $book->changeTotal(new Quantity(1));
})->throws(DomainValidationError::class);

it('soft deletes with the given instant', function () {
    $book = DomainFixtures::book();
    $now = DomainFixtures::at('2026-10-14 10:00');
    $book->softDelete($now);

    expect($book->isDeleted())->toBeTrue()->and($book->deletedAt())->toEqual($now);
});

it('sets and removes the cover, never keeping an empty string', function () {
    $book = DomainFixtures::book();
    $book->setCover('covers/abc.png');
    expect($book->coverKey())->toBe('covers/abc.png');

    $book->removeCover();
    expect($book->coverKey())->toBeNull();

    $book->setCover('');
})->throws(DomainValidationError::class);

it('replaces title, author, category and total, adjusting available copies by the same difference', function () {
    $book = DomainFixtures::book(3);
    $book->withdraw();

    $book->replaceDetails('  Nuevo título ', 'Nuevo autor', 'category-2', new Quantity(5));

    expect($book->title())->toBe('Nuevo título')
        ->and($book->author())->toBe('Nuevo autor')
        ->and($book->categoryId())->toBe('category-2')
        ->and($book->totalCopies())->toBe(5)
        ->and($book->availableCopies())->toBe(4);
});

it('changes nothing when the replacement is rejected', function () {
    $book = DomainFixtures::book(3);
    $book->withdraw();
    $book->withdraw();

    expect(fn () => $book->replaceDetails('Otro', 'Otro autor', 'category-2', new Quantity(1)))
        ->toThrow(DomainValidationError::class);

    expect($book->title())->toBe('Cien años de soledad')
        ->and($book->categoryId())->toBe('category-1')
        ->and($book->totalCopies())->toBe(3)
        ->and($book->availableCopies())->toBe(1);
});
