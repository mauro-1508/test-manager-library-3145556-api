<?php

declare(strict_types=1);

use App\Application\Errors\NotFound;
use App\Domain\BookNotAvailable;
use App\Domain\DomainValidationError;
use App\Domain\DuplicateBookInLoan;
use App\Domain\LoanStatus;
use App\Domain\MemberHasOverdue;
use App\Domain\MemberLimitExceeded;
use Tests\Unit\Application\Fakes\InMemoryCategories;
use Tests\Unit\Application\Fakes\Library;

it('lends books, freezing title, isbn and category and due date at loaned_at + 14 days (CA-06.1, CA-06.6)', function () {
    $lib = new Library('2026-10-14T14:15:00Z');
    $member = $lib->addMember();
    $book = $lib->addBook('Cien años de soledad', 4, InMemoryCategories::NOVEL_ID);

    $view = $lib->lendService()->execute($member->id, [$book->id], 'user-1');

    $item = $view->loan->items()[0];
    expect($view->status)->toBe(LoanStatus::Open)
        ->and($view->loan->memberId)->toBe($member->id)
        ->and($view->loan->loanedByUserId)->toBe('user-1')
        ->and($view->loan->loanedAt->format('Y-m-d\TH:i:s\Z'))->toBe('2026-10-14T14:15:00Z')
        ->and($item->bookTitle)->toBe('Cien años de soledad')
        ->and($item->isbn)->toBe($book->isbn->value)
        ->and($item->categoryName)->toBe('Novela')
        ->and($item->dueAt->format('Y-m-d\TH:i:s\Z'))->toBe('2026-10-28T14:15:00Z')
        ->and($lib->loans->find($view->loan->id))->not->toBeNull();
});

it('takes one copy per line in the same operation (CA-06.3)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $first = $lib->addBook('Uno', 2);
    $second = $lib->addBook('Dos', 1);

    $lib->lendService()->execute($member->id, [$first->id, $second->id], 'user-1');

    expect($lib->books->stored($first->id)->availableCopies())->toBe(1)
        ->and($lib->books->stored($second->id)->availableCopies())->toBe(0);
});

it('registers nothing when one line has no copies left (CA-06.3)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $available = $lib->addBook('Disponible', 2);
    $exhausted = $lib->addBook('Agotado', 1);
    $other = $lib->addMember('Luis');
    $lib->lendService()->execute($other->id, [$exhausted->id], 'user-1');

    try {
        $lib->lendService()->execute($member->id, [$available->id, $exhausted->id], 'user-1');
        $this->fail('Expected BookNotAvailable.');
    } catch (BookNotAvailable $error) {
        expect($error->errorCode())->toBe('BOOK_NOT_AVAILABLE')
            ->and($error->getMessage())->toContain('Agotado')
            ->and($lib->loans->count())->toBe(1)
            ->and($lib->books->stored($available->id)->availableCopies())->toBe(2);
    }
});

it('answers BOOK_NOT_AVAILABLE when the repository reports a concurrency conflict and saves nothing (RNF-2)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $book = $lib->addBook('Último', 1);
    $lib->loans->conflictOnBookId = $book->id;

    expect(fn () => $lib->lendService()->execute($member->id, [$book->id], 'user-1'))
        ->toThrow(BookNotAvailable::class)
        ->and($lib->loans->count())->toBe(0)
        ->and($lib->books->stored($book->id)->availableCopies())->toBe(1);
});

it('rejects the same book twice in one loan (CA-06.2)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $book = $lib->addBook('Repetido', 5);

    expect(fn () => $lib->lendService()->execute($member->id, [$book->id, $book->id], 'user-1'))
        ->toThrow(DuplicateBookInLoan::class)
        ->and($lib->books->stored($book->id)->availableCopies())->toBe(5);
});

it('requires between one and three lines (CA-06.1)', function (int $lines) {
    $lib = new Library();
    $member = $lib->addMember();
    $ids = [];
    for ($i = 0; $i < $lines; $i++) {
        $ids[] = $lib->addBook("Libro {$i}", 2)->id;
    }

    $lib->lendService()->execute($member->id, $ids, 'user-1');
})->with([0, 4])->throws(DomainValidationError::class);

it('answers NOT_FOUND for an unknown member or an unknown book', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $book = $lib->addBook();

    expect(fn () => $lib->lendService()->execute('missing', [$book->id], 'user-1'))->toThrow(NotFound::class)
        ->and(fn () => $lib->lendService()->execute($member->id, [$book->id, 'missing'], 'user-1'))
        ->toThrow(NotFound::class)
        ->and($lib->books->stored($book->id)->availableCopies())->toBe(2);
});

it('does not lend a deleted book', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $book = $lib->addBook();
    $deleted = $lib->books->stored($book->id);
    $deleted->softDelete($lib->clock->now());
    $lib->books->save($deleted);

    $lib->lendService()->execute($member->id, [$book->id], 'user-1');
})->throws(NotFound::class);

it('refuses a member who would exceed 3 unreturned books and reports the current count (CA-06.4)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $lib->lendService()->execute($member->id, [$lib->addBook('A')->id, $lib->addBook('B')->id], 'user-1');
    $next = [$lib->addBook('C')->id, $lib->addBook('D')->id];

    try {
        $lib->lendService()->execute($member->id, $next, 'user-1');
        $this->fail('Expected MemberLimitExceeded.');
    } catch (MemberLimitExceeded $error) {
        expect($error->errorCode())->toBe('MEMBER_LIMIT_EXCEEDED')
            ->and($error->currentCount)->toBe(2)
            ->and($lib->loans->count())->toBe(1);
    }
});

it('lets a member reach exactly 3 unreturned books across loans (CA-06.4)', function () {
    $lib = new Library();
    $member = $lib->addMember();
    $lib->lendService()->execute($member->id, [$lib->addBook('A')->id, $lib->addBook('B')->id], 'user-1');

    $view = $lib->lendService()->execute($member->id, [$lib->addBook('C')->id], 'user-1');

    expect($view->loan->items())->toHaveCount(1);
});

it('refuses a member with an overdue book (CA-06.5)', function () {
    $lib = new Library('2026-10-01T10:00:00Z');
    $member = $lib->addMember();
    $lib->lendService()->execute($member->id, [$lib->addBook('Viejo')->id], 'user-1');
    $lib->clock->set('2026-10-16T10:00:01Z');

    try {
        $lib->lendService()->execute($member->id, [$lib->addBook('Nuevo')->id], 'user-1');
        $this->fail('Expected MemberHasOverdue.');
    } catch (MemberHasOverdue $error) {
        expect($error->errorCode())->toBe('MEMBER_HAS_OVERDUE')
            ->and($lib->loans->count())->toBe(1);
    }
});
